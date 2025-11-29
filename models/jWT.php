<?php


namespace App\Models;
class JWT
{
    public static $table = "access_token";

    static function delete($token, $refreshToken)
    {
        if (empty($token) && empty($refreshToken)) return;
        $tokenUser = \R::findOne(self::$table, 'token = ? or refresh_token = ?', [$token, $refreshToken]);
        if ($tokenUser !== null) {
            \R::trash($tokenUser);
        }

    }

    static function addTokens($id, $token, $refreshToken, $exist)
    {

        $tokenUser = \R::findOrCreate(self::$table, ['user_id' => $id]);
        $tokenUser->token = $token;
        $tokenUser->refresh_token = $refreshToken;
        $tokenUser->time_exist = $exist;
        \R::store($tokenUser);
    }

    static function getUserRefresh($token, $token_refresh)
    {
        $tokenUser = \R::findOne(self::$table, 'token = ? and refresh_token = ?', [$token, $token_refresh]);
        if ($tokenUser) {
            return $tokenUser->user_id;

        }
        return false;
    }
}