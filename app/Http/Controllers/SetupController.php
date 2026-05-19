<?php

namespace App\Http\Controllers;

use App\Classes\JsonResponse;
use App\Http\Requests\ApplicationConfigRequest;
use App\Services\SetupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SetupController extends Controller
{
    public function __construct( private SetupService $setup )
    {
        // ...
    }

    /**
     * Simplified welcome - redirects to quick-setup if not installed
     */
    public function welcome( Request $request )
    {
        if ( Helper::installed() ) {
            return redirect()->route( 'ns.dashboard' );
        }
        
        return view( 'pages.setup.quick-setup', [
            'title' => __( '快速开始 - 收银SaaS' ),
            'languages' => config( 'nexopos.languages' ),
            'lang' => 'zh',
        ] );
    }

    /**
     * Quick Setup - Simplified Registration (Single Step)
     * Skips database and language selection
     */
    public function quickSetup( Request $request )
    {
        if ( Helper::installed() ) {
            return redirect()->route( 'ns.dashboard' );
        }

        return view( 'pages.setup.quick-setup', [
            'title' => __( '快速开始 - 收银SaaS' ),
            'languages' => config( 'nexopos.languages' ),
            'lang' => 'zh',
        ] );
    }

    /**
     * Process Quick Setup - Single API call
     */
    public function processQuickSetup( Request $request )
    {
        return $this->setup->quickInstall( $request->all() );
    }

    /**
     * Check database connectivity
     */
    public function checkDatabase( Request $request )
    {
        return $this->setup->saveDatabaseSettings( $request );
    }

    /**
     * Test existing DB configuration
     */
    public function checkDbConfigDefined( Request $request )
    {
        return $this->setup->testDBConnexion();
    }

    /**
     * Full configuration save (original)
     */
    public function saveConfiguration( ApplicationConfigRequest $request )
    {
        return $this->setup->runMigration( $request->all() );
    }

    /**
     * Check if database is already configured
     */
    public function checkExistingCredentials()
    {
        try {
            if ( DB::connection()->getPdo() ) {
                $this->setup->updateAppURL();
                return JsonResponse::success(
                    message: __( '数据库连接成功。' )
                );
            }
        } catch ( \Exception $e ) {
            return JsonResponse::error(
                message: $e->getMessage()
            );
        }
    }
}