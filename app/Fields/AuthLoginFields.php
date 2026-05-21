<?php

namespace App\Fields;

use App\Classes\Form;
use App\Classes\FormInput;
use App\Classes\Hook;
use App\Services\FieldsService;

class AuthLoginFields extends FieldsService
{
    /**
     * The unique identifier of the form
     **/
    const IDENTIFIER = 'ns.login';

    /**
     * Will ensure the fields are automatically loaded
     **/
    const AUTOLOAD = true;

    public function get()
    {
        $fields = Hook::filter( 'ns-login-fields',
            Form::fields(
                FormInput::text(
                    label: __( '用户名' ),
                    description: __( '请输入您的用户名' ),
                    validation: 'required|min:5',
                    name: 'username',
                    placeholder: __( '请输入用户名' ),
                ),
                FormInput::password(
                    label: __( '密码' ),
                    description: __( '请输入您的密码' ),
                    validation: 'required|min:6',
                    name: 'password',
                    placeholder: __( '请输入密码' ),
                )
            )
        );

        return $fields;
    }
}
