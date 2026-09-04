<?php

use App\Enums\CampaignStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained()->restrictOnDelete();

            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('brief')->nullable();

            // Hashtags obligatoires, contrôlés à la modération.
            $table->json('required_hashtags')->nullable();

            $table->string('status', 32)->default(CampaignStatus::Draft->value);
            $table->char('currency', 3)->default('EUR');

            // --- Budget : tout en centimes entiers, jamais de flottant. ---

            // Plafond absolu. Aucune ligne de code n'a le droit de le dépasser.
            $table->unsignedBigInteger('budget_total_cents');

            // Cache dénormalisé de campaign_budget_transactions.
            // Écrit uniquement par CampaignBudgetService, sous lockForUpdate().
            // Recalculable à tout moment via `php artisan budget:audit`.
            $table->unsignedBigInteger('spent_cents')->default(0);

            $table->unsignedBigInteger('target_views')->nullable();

            // Seuil de vues sous lequel un clip n'est pas rémunéré.
            $table->unsignedInteger('min_views_per_clip')->default(0);

            // Garde-fous anti-abus, optionnels par campagne.
            $table->unsignedBigInteger('max_payout_per_clip_cents')->nullable();
            $table->unsignedBigInteger('max_payout_per_clipper_cents')->nullable();

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('exhausted_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Validation manuelle des participations avant publication.
            $table->boolean('requires_approval')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'starts_at', 'ends_at']);
            $table->index('creator_id');
        });

        // Dernier rempart au niveau du moteur de base : même si un jour du code
        // contourne le service, MySQL refuse l'écriture.
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE campaigns ADD CONSTRAINT chk_campaigns_budget_not_exceeded '
                .'CHECK (spent_cents <= budget_total_cents)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
