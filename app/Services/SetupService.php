<?php

namespace App\Services;

use App\Events\UserAfterActivationSuccessfulEvent;
use App\Models\PaymentType;
use App\Models\User;
use App\Services\Options;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SetupService
{
    public Options $options;

    /**
     * Quick Install - Simplified Single Step Setup
     * Skips database config (uses pre-configured .env) and language selection
     * Only requires: username, email, password, store name, industry
     */
    public function quickInstall( $fields )
    {
        /**
         * Set language to Chinese by default
         */
        $configuredLanguage = 'zh';
        App::setLocale( $configuredLanguage );

        /**
         * Run migrations
         */
        Artisan::call( 'migrate', [
            '--force' => true,
        ] );

        /**
         * Publish Sanctum
         */
        Artisan::call( 'vendor:publish', [
            '--force' => true,
            '--provider' => 'Laravel\Sanctum\SanctumServiceProvider',
        ] );

        Artisan::call( 'ns:translate', [
            '--symlink' => true,
        ] );

        /**
         * Execute core migrations
         */
        ns()->update
            ->getMigrations(
                directories: [ 'core', 'create' ],
                ignoreMigrations: true
            )
            ->each( function ( $file ) {
                ns()->update->executeMigrationFromFileName( $file );
            } );

        /**
         * Assume update migrations already executed
         */
        ns()->update
            ->getMigrations(
                directories: [ 'update' ],
                ignoreMigrations: true
            )
            ->each( function ( $file ) {
                ns()->update->assumeExecuted( $file );
            } );

        /**
         * Clear cache
         */
        Artisan::call( 'cache:clear' );

        /**
         * Register permissions
         */
        ns()->registerGatePermissions();

        /**
         * Create admin user
         */
        $userID = rand( 1, 99 );
        $user = new User;
        $user->id = $userID;
        $user->username = $fields[ 'admin_username' ] ?? 'admin';
        $user->password = Hash::make( $fields[ 'password' ] );
        $user->email = $fields[ 'admin_email' ] ?? 'admin@example.com';
        $user->author_id = $userID;
        $user->active = true;
        $user->save();
        $user->assignRole( 'admin' );

        /**
         * Set user language
         */
        $user->attribute()->create( [
            'language' => $configuredLanguage,
        ] );

        UserAfterActivationSuccessfulEvent::dispatch( $user );

        /**
         * Create default payments and accounting
         */
        $this->createDefaultPayment( $user );
        $this->createDefaultAccounting();

        /**
         * Save industry type
         */
        $this->options = app()->make( Options::class );
        $this->options->setDefault();
        $this->options->set( 'ns_store_language', $configuredLanguage );
        $this->options->set( 'ns_store_name', $fields[ 'ns_store_name' ] ?? '我的店铺' );
        $this->options->set( 'ns_industry_type', $fields[ 'industry_type' ] ?? 'retail' );

        return [
            'status' => 'success',
            'message' => __( '安装成功！正在跳转...' ),
        ];
    }

    public function createDefaultAccounting()
    {
        $service = app()->make( TransactionService::class );
        $service->createDefaultAccounts();
    }

    public function createDefaultPayment( $user )
    {
        $cashPaymentType = new PaymentType;
        $cashPaymentType->label = __( '现金' );
        $cashPaymentType->identifier = 'cash-payment';
        $cashPaymentType->readonly = true;
        $cashPaymentType->author_id = $user->id;
        $cashPaymentType->save();

        $bankPaymentType = new PaymentType;
        $bankPaymentType->label = __( '银行卡' );
        $bankPaymentType->identifier = 'bank-payment';
        $bankPaymentType->readonly = true;
        $bankPaymentType->author_id = $user->id;
        $bankPaymentType->save();

        $customerAccountType = new PaymentType;
        $customerAccountType->label = __( '会员账户' );
        $customerAccountType->identifier = 'account-payment';
        $customerAccountType->readonly = true;
        $customerAccountType->author_id = $user->id;
        $customerAccountType->save();
    }

    public function testDBConnexion()
    {
        try {
            $DB = DB::connection( env( 'DB_CONNECTION', 'mysql' ) )->getPdo();
            return [
                'status' => 'success',
                'message' => __( '数据库连接成功。' ),
            ];
        } catch ( \Exception $e ) {
            return response()->json( [
                'name' => 'hostname',
                'message' => $e->getMessage(),
                'status' => 'error',
            ], 403 );
        }
    }
}