<?php

use App\Enums\ClipStatus;
use App\Enums\ParticipationStatus;
use App\Enums\PayoutStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CONTRAT DE DONNÉES — module clippeur.
 *
 * Ces tables appartiennent fonctionnellement au module clippeur (Anas).
 * Elles sont définies ici pour que le moteur de budget soit développable et
 * testable dès maintenant : ce sont les colonnes minimales dont il a besoin.
 * Anas peut les étendre ; il ne doit pas renommer ni supprimer ce qui suit.
 *
 * FRONTIÈRE D'ÉCRITURE — non négociable :
 *   - le module clippeur écrit clips.views_total et clip_view_snapshots ;
 *   - clips.paid_views et clips.earned_cents sont en LECTURE SEULE pour lui,
 *     seul CampaignBudgetService les écrit, sous verrou ;
 *   - aucune écriture directe sur campaigns.spent_cents, jamais.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 32);

            // Identifiant du compte chez la plateforme, stable dans le temps.
            $table->string('external_account_id');
            $table->string('handle')->nullable();

            // Jetons OAuth : chiffrés au niveau du modèle (cast 'encrypted').
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();

            $table->unsignedBigInteger('followers_count')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['platform', 'external_account_id']);
            $table->index(['user_id', 'platform']);
        });

        Schema::create('campaign_participations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();

            $table->string('status', 32)->default(ParticipationStatus::Pending->value);
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            // Un compte social ne rejoint une campagne qu'une fois.
            $table->unique(['campaign_id', 'social_account_id']);
            $table->index(['campaign_id', 'status']);
        });

        Schema::create('clips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participation_id')->nullable()->constrained('campaign_participations')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_account_id')->nullable()->constrained()->nullOnDelete();

            $table->string('platform', 32);
            $table->string('external_post_id');
            $table->string('url', 2048);
            $table->timestamp('posted_at')->nullable();

            $table->string('status', 32)->default(ClipStatus::PendingReview->value);
            $table->text('rejection_reason')->nullable();

            // --- Écrit par le module clippeur ---
            $table->unsignedBigInteger('views_total')->default(0);
            $table->timestamp('last_synced_at')->nullable();

            // --- Écrit UNIQUEMENT par CampaignBudgetService ---
            // Vues déjà rémunérées. La différence avec views_total est le delta
            // à créditer au prochain passage.
            $table->unsignedBigInteger('paid_views')->default(0);
            $table->unsignedBigInteger('earned_cents')->default(0);

            $table->timestamps();

            // Un même post ne peut pas être soumis deux fois.
            $table->unique(['platform', 'external_post_id']);
            $table->index(['campaign_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('clip_view_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clip_id')->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('views');

            // 'api' | 'scraper' | 'manual' — utile pour tracer une anomalie.
            $table->string('source', 32)->default('api');
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->index(['clip_id', 'captured_at']);
        });

        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            $table->unsignedBigInteger('amount_cents');
            $table->char('currency', 3)->default('EUR');
            $table->string('status', 32)->default(PayoutStatus::Requested->value);

            $table->string('paypal_email');
            $table->string('paypal_batch_id')->nullable();
            $table->string('paypal_payout_item_id')->nullable();

            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('failure_reason')->nullable();

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('paypal_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('clip_view_snapshots');
        Schema::dropIfExists('clips');
        Schema::dropIfExists('campaign_participations');
        Schema::dropIfExists('social_accounts');
    }
};
