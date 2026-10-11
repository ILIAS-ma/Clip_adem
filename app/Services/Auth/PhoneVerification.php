<?php

namespace App\Services\Auth;

use App\Exceptions\SmsFailed;
use App\Models\User;
use App\Services\Sms\SmsManager;
use App\Support\CodeEnvoye;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Vérification d'un numéro de téléphone par code à usage unique.
 *
 * Trois protections, chacune contre une attaque différente :
 *
 *  - le code est stocké **haché**, jamais en clair : une fuite de la base
 *    donnerait sinon un accès immédiat à tout compte en cours de vérification ;
 *  - un **délai** entre deux envois : sans lui, un bouton « renvoyer » permet
 *    de faire pleuvoir des SMS sur un numéro qu'on ne possède pas — et c'est
 *    nous qui payons chaque message ;
 *  - un **nombre d'essais** borné : six chiffres, c'est un million de
 *    combinaisons, mais quelques milliers de tentatives suffisent à en
 *    trouver une si rien ne les compte.
 */
class PhoneVerification
{
    public const LONGUEUR = 6;

    public function __construct(protected SmsManager $sms) {}

    /** Secondes à patienter avant un nouvel envoi. */
    public function delaiRestant(User $user): int
    {
        if (! $user->phone_code_sent_at) {
            return 0;
        }

        $attente = (int) config('clipping.phone.resend_seconds');

        return max(0, $attente - $user->phone_code_sent_at->diffInSeconds(now()));
    }

    /**
     * Envoie un code au numéro donné.
     *
     * @throws \RuntimeException|SmsFailed
     */
    public function envoyer(User $user, string $numero, ?string $pays = null): CodeEnvoye
    {
        $normalise = PhoneNumber::normalise($numero, $pays ?? $user->country ?? 'FR');

        if ($normalise === null) {
            throw new \RuntimeException('Ce numéro ne peut pas être lu.');
        }

        if ($this->delaiRestant($user) > 0) {
            throw new \RuntimeException('Un code vient déjà d’être envoyé.');
        }

        $code = str_pad((string) random_int(0, 10 ** self::LONGUEUR - 1), self::LONGUEUR, '0', STR_PAD_LEFT);

        // Le numéro est enregistré avant l'envoi : si le SMS échoue, on doit
        // savoir à qui l'on a tenté d'écrire pour pouvoir réessayer.
        $user->forceFill([
            'phone' => $normalise,
            'phone_verified_at' => null,
            // Le cast `hashed` du modèle s'en charge : hacher ici aussi
            // ferait deux mécanismes pour la même chose, et le jour où
            // l'un change, l'autre casse en silence.
            'phone_code_hash' => $code,
            'phone_code_sent_at' => now(),
            'phone_code_attempts' => 0,
        ])->save();

        $pilote = $this->sms->driver();

        $pilote->send($normalise, sprintf(
            '%s : votre code de vérification est %s. Il expire dans %d minutes.',
            config('app.name'),
            $code,
            (int) config('clipping.phone.code_minutes'),
        ));

        Log::info('Code de vérification envoyé', [
            'user_id' => $user->getKey(),
            'phone' => PhoneNumber::masque($normalise),
            'pilote' => $pilote->name(),
        ]);

        /*
         * Le code remonte uniquement si le pilote n'a rien envoyé. Annoncer
         * « un code vient de vous être envoyé par SMS » alors qu'il n'est
         * écrit que dans storage/logs laisse la personne attendre un message
         * qui n'existe pas — et nous ne le saurions pas.
         */
        return new CodeEnvoye($normalise, $pilote->isSimulated() ? $code : null);
    }

    /** Le code proposé est-il le bon ? Marque le numéro vérifié si oui. */
    public function verifier(User $user, string $code): bool
    {
        if (! $user->phone_code_hash || ! $user->phone_code_sent_at) {
            return false;
        }

        $validite = (int) config('clipping.phone.code_minutes');

        if ($user->phone_code_sent_at->addMinutes($validite)->isPast()) {
            return false;
        }

        if ($user->phone_code_attempts >= (int) config('clipping.phone.max_attempts')) {
            return false;
        }

        // Le compteur monte avant la comparaison : s'il montait après, une
        // requête interrompue au bon moment ne serait jamais comptée.
        $user->increment('phone_code_attempts');

        if (! Hash::check($code, $user->phone_code_hash)) {
            return false;
        }

        $user->forceFill([
            'phone_verified_at' => now(),
            'phone_code_hash' => null,
            'phone_code_attempts' => 0,
        ])->save();

        return true;
    }
}
