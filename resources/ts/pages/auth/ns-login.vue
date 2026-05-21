<template>
    <div>
        <div class="flex items-center justify-center py-6" v-if="fields.length === 0">
            <div class="flex items-center">
                <ns-spinner border="4" size="16"></ns-spinner>
                <span class="ml-2 text-gray-500 text-sm">{{ __( '正在加载...' ) }}</span>
            </div>
        </div>
        <div v-if="fields.length > 0" @keyup.enter="signIn()">
            <ns-field :key="index" v-for="(field, index) of fields" :field="field"></ns-field>
            <div class="flex items-center justify-between mt-4">
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" v-model="rememberMe" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span class="ml-2 text-sm text-gray-600">{{ __( '记住我' ) }}</span>
                </label>
                <a v-if="showRecoveryLink" href="/password-lost" class="text-sm text-blue-600 hover:text-blue-700 hover:underline">{{ __( '忘记密码？' ) }}</a>
            </div>
            <div class="mt-6">
                <ns-button :disabled="isSubitting" @click="signIn()" class="w-full justify-center" type="primary">
                    <ns-spinner class="mr-2" v-if="isSubitting" size="6"></ns-spinner>
                    <span>{{ __( '登 录' ) }}</span>
                </ns-button>
            </div>
            <div v-if="showRegisterButton" class="mt-4 text-center">
                <span class="text-gray-500 text-sm">{{ __( '还没有账户？' ) }}</span>
                <a href="/sign-up" class="ml-1 text-sm text-blue-600 hover:text-blue-700 hover:underline">{{ __( '立即注册' ) }}</a>
            </div>
        </div>
    </div>
</template>
<script>
import { forkJoin } from 'rxjs';
import FormValidation from '~/libraries/form-validation';
import { nsHooks, nsHttpClient, nsSnackBar } from '~/bootstrap';
import { __ } from '~/libraries/lang';
export default {
    name: 'ns-login',
    props: [ 'showRecoveryLink', 'showRegisterButton' ],
    data() {
        return {
            fields: [],
            xXsrfToken: null,
            validation: new FormValidation,
            isSubitting: false,
            rememberMe: false,
        }
    },
    mounted() {
        const savedUsername = localStorage.getItem('ns-remember-username');
        if (savedUsername) {
            this.rememberMe = true;
        }
        
        forkJoin({
            login: nsHttpClient.get( '/api/fields/ns.login' ),
            csrf: nsHttpClient.get( '/sanctum/csrf-cookie' ),
        })
        .subscribe({
            next: result => {
                this.fields         =   this.validation.createFields( result.login );
                this.xXsrfToken     =   nsHttpClient.response.config.headers[ 'X-XSRF-TOKEN' ];
                
                if (savedUsername) {
                    const usernameField = this.fields.find(f => f.name === 'username');
                    if (usernameField) {
                        usernameField.value = savedUsername;
                    }
                }

                setTimeout( () => nsHooks.doAction( 'ns-login-mounted', this ), 100 );
            },
            error: ( error ) => {
                nsSnackBar.error( error.message || __( '发生未知错误，请刷新页面重试。' ), __( '确定' ), { duration: 0 });
            }
        });
    },
    methods: {
        __,
        signIn() {
            const isValid   =   this.validation.validateFields( this.fields );

            if ( ! isValid ) {
                return nsSnackBar.error( __( '表单填写不正确，请检查。' ) );
            }

            this.validation.disableFields( this.fields );

            if ( nsHooks.applyFilters( 'ns-login-submit', true ) ) {
                this.isSubitting    =   true;
                
                const formData = this.validation.getValue( this.fields );
                
                if (this.rememberMe) {
                    localStorage.setItem('ns-remember-username', formData.username);
                } else {
                    localStorage.removeItem('ns-remember-username');
                }
                
                nsHttpClient.post( '/auth/sign-in', formData, {
                    headers: {
                        'X-XSRF-TOKEN'  : this.xXsrfToken
                    }
                }).subscribe({
                    next: (result) => {
                        document.location   =   result.data.redirectTo;
                    },
                    error: ( error ) => {
                        this.isSubitting    =   false;
                        this.validation.enableFields( this.fields );

                        if ( error.data ) {
                            this.validation.triggerFieldsErrors( this.fields, error.data );
                        }

                        nsSnackBar.error( error.message || __( '登录失败，请检查用户名和密码。' ) );
                    }
                })
            }
        }
    }
}
</script>
