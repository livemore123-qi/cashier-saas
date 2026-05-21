<?php

use App\Classes\Hook;
use App\Classes\Output;
use App\Events\AfterLoginFieldsEvent;
use App\Events\BeforeLoginFieldsEvent;
use App\Events\RenderLoginFooterEvent;
?>
@extends( 'layout.base' )

@section( 'layout.base.body' )
    <div id="page-container" class="h-full w-full flex items-center overflow-y-auto pb-10 bg-gradient-to-br from-blue-50 to-indigo-100">
        <div class="container mx-auto p-4 md:p-0 flex-auto items-center justify-center flex">
            <div id="sign-in-box" class="w-full md:w-3/5 lg:w-2/5 xl:w-96">
                <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-8">
                        <div class="flex justify-center items-center">
                            @if ( ! ns()->option->get( 'ns_store_square_logo', false ) )
                            <img class="w-16 h-16 rounded-full bg-white p-2" src="{{ asset( 'svg/nexopos-variant-1.svg' ) }}" alt="收银系统">
                            @else
                            <img class="w-16 h-16 rounded-full bg-white p-2" src="{{ ns()->option->get( 'ns_store_square_logo' ) }}" alt="收银系统">
                            @endif
                        </div>
                        <h1 class="text-center text-white text-2xl font-bold mt-4">{{ __( '收银系统' ) }}</h1>
                        <p class="text-center text-blue-100 text-sm mt-2">{{ __( '欢迎回来，请登录您的账户' ) }}</p>
                    </div>
                    <div class="p-6">
                        <x-session-message></x-session-message>
                        {!! Output::dispatch( BeforeLoginFieldsEvent::class ) !!}
                        @include( '/common/auth/sign-in-form' )
                        {!! Output::dispatch( AfterLoginFieldsEvent::class ) !!}
                    </div>
                    <div class="bg-gray-50 px-6 py-4 text-center">
                        <p class="text-gray-500 text-sm">{{ __( '如有问题请联系管理员' ) }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section( 'layout.base.footer' )
    @parent
    {!! Output::dispatch( RenderLoginFooterEvent::class ) !!}
    @vite([ 'resources/ts/auth.ts' ])
@endsection