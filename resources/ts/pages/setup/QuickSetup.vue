<template>
    <div class="w-full md:w-3/5 lg:w-3/5 self-center">
        <div class="bg-white rounded shadow my-2 overflow-hidden">
            <div class="welcome-box border-b border-gray-300 p-3 text-gray-700">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">{{ __( '快速开始' ) }}</h2>
                <p class="text-gray-600 text-sm mb-4">{{ __( '填写以下信息，即可开始使用收银系统。' ) }}</p>
            </div>
            
            <div class="p-4">
                <div class="space-y-4">
                    <!-- 店铺名称 -->
                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __( '店铺名称' ) }}</label>
                        <input 
                            type="text" 
                            v-model="form.ns_store_name"
                            class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                            :placeholder="__( '请输入店铺名称' )"
                        />
                    </div>
                    
                    <!-- 行业类型 -->
                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __( '所属行业' ) }}</label>
                        <select 
                            v-model="form.industry_type"
                            class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="retail">{{ __( '零售便利店' ) }}</option>
                            <option value="restaurant">{{ __( '餐饮店' ) }}</option>
                            <option value=" supermarket">{{ __( '超市' ) }}</option>
                            <option value="salon">{{ __( '美容美发' ) }}</option>
                            <option value="clothing">{{ __( '服装店' ) }}</option>
                            <option value="pharmacy">{{ __( '药店' ) }}</option>
                            <option value="other">{{ __( '其他行业' ) }}</option>
                        </select>
                    </div>
                    
                    <!-- 用户名 -->
                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __( '管理员账号' ) }}</label>
                        <input 
                            type="text" 
                            v-model="form.admin_username"
                            class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                            :placeholder="__( '请输入管理员账号' )"
                        />
                    </div>
                    
                    <!-- 邮箱 -->
                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __( '电子邮箱' ) }}</label>
                        <input 
                            type="email" 
                            v-model="form.admin_email"
                            class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                            :placeholder="__( '请输入电子邮箱' )"
                        />
                    </div>
                    
                    <!-- 密码 -->
                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __( '密码' ) }}</label>
                        <input 
                            type="password" 
                            v-model="form.password"
                            class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                            :placeholder="__( '请输入密码' )"
                        />
                    </div>
                    
                    <!-- 确认密码 -->
                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __( '确认密码' ) }}</label>
                        <input 
                            type="password" 
                            v-model="form.confirm_password"
                            class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                            :placeholder="__( '请再次输入密码' )"
                        />
                    </div>
                </div>
            </div>
            
            <div class="bg-gray-200 p-3 flex justify-end">
                <button 
                    @click="submitForm"
                    :disabled="processing"
                    class="ns-button info rounded cursor-pointer py-2 px-6 font-semibold flex items-center"
                >
                    <span v-if="processing" class="mr-2">
                        <ns-spinner :size="4"></ns-spinner>
                    </span>
                    <i class="las la-check"></i> 
                    {{ __( '完成安装' ) }}
                </button>
            </div>
        </div>
    </div>
</template>

<script lang="ts">
import { nsHttpClient, nsSnackBar } from "~/bootstrap";

export default {
    data() {
        return {
            form: {
                ns_store_name: '',
                industry_type: 'retail',
                admin_username: '',
                admin_email: '',
                password: '',
                confirm_password: '',
            },
            processing: false,
        }
    },
    methods: {
        submitForm() {
            // Basic validation
            if (!this.form.ns_store_name) {
                nsSnackBar.error( '请输入店铺名称', 'OK' ).open();
                return;
            }
            if (!this.form.admin_username) {
                nsSnackBar.error( '请输入管理员账号', 'OK' ).open();
                return;
            }
            if (!this.form.admin_email) {
                nsSnackBar.error( '请输入电子邮箱', 'OK' ).open();
                return;
            }
            if (!this.form.password) {
                nsSnackBar.error( '请输入密码', 'OK' ).open();
                return;
            }
            if (this.form.password !== this.form.confirm_password) {
                nsSnackBar.error( '两次输入的密码不一致', 'OK' ).open();
                return;
            }
            
            this.processing = true;
            
            nsHttpClient.post( '/api/setup/quick-setup', this.form )
                .subscribe({
                    next: result => {
                        nsSnackBar.success( result.message, 'OK' ).open();
                        setTimeout(() => {
                            document.location = '/sign-in';
                        }, 1000);
                    },
                    error: error => {
                        this.processing = false;
                        nsSnackBar.error( error.message || '安装失败', 'OK' ).open();
                    }
                });
        }
    },
    mounted() {
        // Set default language to Chinese
        if (typeof ns !== 'undefined') {
            ns.language = 'zh';
        }
    }
}
</script>

<style scoped>
.ns-button {
    background: #3b82f6;
    color: white;
}
.ns-button:hover {
    background: #2563eb;
}
.ns-button:disabled {
    background: #93c5fd;
    cursor: not-allowed;
}
</style>