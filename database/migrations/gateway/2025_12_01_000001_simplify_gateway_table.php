<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Desabilitar verificação de foreign keys temporariamente
        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        }

        // Dropar tabelas relacionadas na ordem correta (foreign keys)
        Schema::dropIfExists('gateway_webhook_log');
        Schema::dropIfExists('gateway_payment_link');
        Schema::dropIfExists('gateway_card_token');
        Schema::dropIfExists('gateway_refund');
        Schema::dropIfExists('gateway_subscription');
        Schema::dropIfExists('gateway_charge');
        Schema::dropIfExists('gateway_customer');
        Schema::dropIfExists('gateway_billing_type');

        // Dropar tabela gateway antiga
        Schema::dropIfExists('gateway');

        // Recriar tabela gateway com estrutura simplificada
        Schema::create('gateway', function (Blueprint $table) {
            $table->id();
            $table->string('label'); // Identificação do usuário (ex: "Asaas Produção")
            $table->string('provider_class'); // FQCN (ex: App\Services\Gateways\Providers\AsaasGateway)
            $table->enum('mode', ['sandbox', 'live'])->default('sandbox');
            $table->json('credentials')->nullable(); // {keys: {api_key, webhook_secret}, config: {...}}
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['provider_class', 'mode']);
        });

        // Recriar tabelas relacionadas com a nova estrutura

        Schema::create('gateway_billing_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gateway_id')->constrained('gateway')->cascadeOnDelete();
            $table->foreignId('billing_type_id')->constrained('billing_type')->cascadeOnDelete();
            $table->string('gateway_code')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['gateway_id', 'billing_type_id']);
            $table->index(['gateway_id', 'gateway_code']);
        });

        Schema::create('gateway_customer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gateway_id')->constrained('gateway')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('gateway_customer_id')->index();
            $table->string('name');
            $table->string('cpf_cnpj', 14)->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['gateway_id', 'gateway_customer_id']);
            $table->index(['gateway_id', 'user_id']);
        });

        Schema::create('gateway_charge', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gateway_id')->constrained('gateway')->cascadeOnDelete();
            $table->foreignId('gateway_customer_id')->nullable()->constrained('gateway_customer')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('gateway_charge_id')->index();
            $table->string('gateway_installment_id')->nullable()->index();
            $table->string('gateway_subscription_id')->nullable()->index();
            $table->foreignId('subscription_id')->nullable()->constrained('subscription')->nullOnDelete();
            $table->string('billing_type');
            $table->string('charge_type')->default('detached');
            $table->string('status');
            $table->decimal('amount', 15, 2);
            $table->decimal('net_amount', 15, 2)->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('description')->nullable();
            $table->string('external_reference')->nullable();
            $table->string('invoice_url')->nullable();
            $table->text('pix_payload')->nullable();
            $table->text('pix_qr_code_base64')->nullable();
            $table->timestamp('pix_expiration_at')->nullable();
            $table->string('boleto_url')->nullable();
            $table->string('boleto_digitable_line')->nullable();
            $table->string('boleto_bar_code')->nullable();
            $table->integer('installment_number')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['gateway_id', 'gateway_charge_id']);
            $table->index(['gateway_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('external_reference');
            $table->index('subscription_id');
        });

        Schema::create('gateway_subscription', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gateway_id')->constrained('gateway')->cascadeOnDelete();
            $table->foreignId('gateway_customer_id')->nullable()->constrained('gateway_customer')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('gateway_subscription_id')->index();
            $table->foreignId('subscription_id')->nullable()->constrained('subscription')->nullOnDelete();
            $table->string('billing_type');
            $table->string('status');
            $table->string('cycle');
            $table->decimal('amount', 15, 2);
            $table->date('next_due_date');
            $table->date('end_date')->nullable();
            $table->integer('max_payments')->nullable();
            $table->string('description')->nullable();
            $table->string('external_reference')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['gateway_id', 'gateway_subscription_id']);
            $table->index(['gateway_id', 'status']);
            $table->index('subscription_id');
        });

        Schema::create('gateway_refund', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gateway_charge_id')->constrained('gateway_charge')->cascadeOnDelete();
            $table->string('gateway_refund_id')->nullable()->index();
            $table->string('status');
            $table->decimal('amount', 15, 2);
            $table->string('description')->nullable();
            $table->string('reason')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('raw_data')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('gateway_card_token', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gateway_id')->constrained('gateway')->cascadeOnDelete();
            $table->foreignId('gateway_customer_id')->nullable()->constrained('gateway_customer')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token');
            $table->string('brand')->nullable();
            $table->string('last_four', 4)->nullable();
            $table->boolean('is_default')->default(false);
            $table->json('raw_data')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_default']);
        });

        Schema::create('gateway_payment_link', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gateway_id')->constrained('gateway')->cascadeOnDelete();
            $table->string('gateway_link_id')->index();
            $table->string('name');
            $table->string('billing_type');
            $table->string('charge_type');
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('url');
            $table->boolean('is_active')->default(true);
            $table->string('external_reference')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['gateway_id', 'gateway_link_id']);
        });

        Schema::create('gateway_webhook_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gateway_id')->nullable()->constrained('gateway')->nullOnDelete();
            $table->string('webhook_id')->nullable();
            $table->string('event');
            $table->string('gateway_event')->nullable();
            $table->string('gateway_charge_id')->nullable()->index();
            $table->string('gateway_subscription_id')->nullable()->index();
            $table->string('gateway_link_id')->nullable()->index();
            $table->json('payload');
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        // Reabilitar verificação de foreign keys
        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }

    public function down(): void
    {
        // Desabilitar verificação de foreign keys temporariamente
        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        }

        // Dropar tabelas na ordem reversa
        Schema::dropIfExists('gateway_webhook_log');
        Schema::dropIfExists('gateway_payment_link');
        Schema::dropIfExists('gateway_card_token');
        Schema::dropIfExists('gateway_refund');
        Schema::dropIfExists('gateway_subscription');
        Schema::dropIfExists('gateway_charge');
        Schema::dropIfExists('gateway_customer');
        Schema::dropIfExists('gateway_billing_type');
        Schema::dropIfExists('gateway');

        // Reabilitar verificação de foreign keys
        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }
};
