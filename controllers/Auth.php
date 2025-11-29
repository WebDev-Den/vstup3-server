<?php

namespace App\Controllers;
class ControllerAuth extends Controller
{

    function query_PostLogout()
    {
        $token_refresh = $this->data_query['refreshToken'];
        $token = \App\Core\Validate::getAuthorizationBearer();
        \App\Models\JWT::delete($token ?? null, $token_refresh ?? null);
        return true;
    }

    function query_PutActive()
    {
        $user = \App\Core\Validate::roles('admin');

        if (!isset($this->data_query['isActive'])) {
            \App\Core\Response::badRequest("isActive empty", "invalid_data");
        }
        if (empty($this->data_query['userId'])) {
            \App\Core\Response::badRequest("userId empty", "invalid_data");
        }
        $isActive = boolval($this->data_query['isActive']);
        $userID = intval($this->data_query['userId']);
        return \App\Models\Auth::setActive($userID, $isActive);
    }

    function query_PutRole()
    {
        $user = \App\Core\Validate::roles('admin');

        if (empty($this->data_query['roleId'])) {
            \App\Core\Response::badRequest("Role id empty", "invalid_data");
        }
        if (empty($this->data_query['userId'])) {
            \App\Core\Response::badRequest("User id empty", "invalid_data");
        }

        $role = intval($this->data_query['roleId']);
        $userID = intval($this->data_query['userId']);


        $role_out = 'user';
        switch ($role) {
            case 2:
                $role_out = 'admin';
                break;

        }
        return \App\Models\Auth::setRole($userID, $role_out);
    }

    function query_GetUsers()
    {
        $user = \App\Core\Validate::roles('admin');
        return \App\Models\Auth::getUsersList($this->data_query["page"] ?? 1, $this->data_query['limit'] ?? 10);

    }

    function query_GetProfile()
    {
        $user = \App\Core\Validate::roles();
        unset($user['code']);
        unset($user['iat']);
        unset($user['exp']);
        return ['user' => $user];
    }

    function query_PostLogin($create = false)
    {
        $email = $this->data_query['email'];
        $password = $this->data_query['password'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
            \App\Core\Response::badRequest("Email invalid", "email_invalid");
        }
        if (strlen($password) < 6) {
            \App\Core\Response::badRequest("Password length is less than 6 characters", "password_length");
        }
        $user = \App\Models\Auth::login($email, $password);
        if (!$user['id']) \App\Core\Response::badRequest("Server error during authorisation", "server_error");
        $res = $this->getTokens($user);

        if ($create) {
            \App\Core\Response::created([
                'tokens' => $res,
                'user' => $user
            ]);

        }

        return [
            'tokens' => $res,
            'user' => $user
        ];
    }

    function query_PostRegister()
    {
        $email = $this->data_query['email'];
        $password = $this->data_query['password'];
        $fio = $this->data_query['fio'];
        $specialty = $this->data_query['specialty'];


        if (!filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
            \App\Core\Response::badRequest("Email invalid", "email_invalid");
        }
        if (strlen($password) < 6) {
            \App\Core\Response::badRequest("Password length is less than 6 characters", "password_length");
        }
        if (strlen($fio) < 3) {
            \App\Core\Response::badRequest("Full name must be more than 3 characters", "fio_error");
        }
        if (strlen($specialty) < 2) {
            \App\Core\Response::badRequest("Specialty must be more than 2 characters", "specialty_error");
        }

        $userID = \App\Models\Auth::register($email, $password, $fio, $specialty);
        if ($userID) {
            return $this->query_PostLogin(true);
        }
        return $userID;
    }

    function query_PostRefresh()
    {
        $token_refresh = $this->data_query['refreshToken'];
        if (empty($token_refresh) || !isset($token_refresh)) {
            \App\Core\Response::badRequest("Refresh token not exist", "refresh_token_error");
        }

        global $JWT;
        $res = $JWT->validateToken($token_refresh);
        if (!isset($res->token) || !isset($res->code)) {
            \App\Core\Response::badRequest("Refresh token invalid", "refresh_token_error");
        }
        $isValid = \App\Core\PasswordManager::verify(\App\Core\Validate::getClientIP(), $res->code);
        if (!$isValid) {
            \App\Core\Response::badRequest("Refresh token expired", "refresh_token_error");
        }
        $token = \App\Core\Validate::getAuthorizationBearer();


        $userID = \App\Models\JWT::getUserRefresh($token, $token_refresh);
        if (!$userID) {
            \App\Core\Response::badRequest("Access token invalid", "refresh_token_error");
        }
        $user = \App\Models\Auth::getUserByID($userID);
        if (!$user) {
            \App\Core\Response::badRequest("User not found", "user_not_found");
        }
        if (!$user['id']) \App\Core\Response::badRequest("Server error during authorisation", "server_error");
        $res = $this->getTokens($user);
        return [
            'tokens' => $res,
            'user' => $user
        ];
    }

}
