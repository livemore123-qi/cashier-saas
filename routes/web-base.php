<?php

use App\Http\Controllers\SetupController;
use App\Services\Helper;
use Illuminate\Support\Facades\Route;

if ( ! Helper::installed() ) {
    /**
     * Setup Routes - Simplified to single step
     */
    Route::prefix( '/do-setup/' )->group( function () {
        Route::get( '', [ SetupController::class, 'welcome' ] )->name( 'ns.do-setup' );
        Route::get( 'quick-setup', [ SetupController::class, 'quickSetup' ] )->name( 'ns.quick-setup' );
    } );
    
    /**
     * API Setup Routes
     */
    Route::prefix( 'api/setup/' )->group( function () {
        Route::post( 'quick-setup', [ SetupController::class, 'processQuickSetup' ] )->name( 'ns.api.quick-setup' );
        Route::get( 'check-database', [ SetupController::class, 'checkExistingCredentials' ] );
    } );
}