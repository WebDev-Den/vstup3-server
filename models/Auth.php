<?php


namespace App\Models;
class Auth
{
    public static $table = "users";

    static function setActive($user_id, $isActive)
    {
        $user = \R::findOne(self::$table, 'id = ?', [$user_id]);
        if ($user !== null) {
            $user->is_active = $isActive ? 1 : 0;
            return \R::store($user);
        }
        return false;
    }

    static function setRole($user_id, $role, $faculty_id = null)
    {
        $user = \R::findOne(self::$table, 'id = ?', [$user_id]);
        if ($user !== null) {
            $user->role = $role;

            // Для деканів обов'язково встановлюємо faculty_id
            if ($role === 'dean') {
                if (empty($faculty_id)) {
                    // Якщо не передано faculty_id для декана - помилка
                    return false;
                }
                $user->faculty_id = $faculty_id;
            } else {
                // Для інших ролей очищуємо faculty_id
                $user->faculty_id = null;
            }

            return \R::store($user);
        }
        return false;
    }

    static function getUsersList($page, $limit)
    {
        $offset = ($page - 1) * $limit;

        $total = \R::count(self::$table);

        $totalPages = ceil($total / $limit);

        $users = \R::findAll(self::$table, 'ORDER BY id LIMIT ? OFFSET ?', [$limit, $offset]);

        $out = [];

        foreach ($users as $user) {
            // Визначення role_id на основі role_name
            $role_id = 1; // user за замовчуванням
            if ($user->role === 'admin') {
                $role_id = 2;
            } elseif ($user->role === 'dean') {
                $role_id = 3;
            }

            $out[] = [
                'id' => (int)$user->id,
                'email' => $user->email,
                'fio' => $user->fio,
                'specialty' => $user->specialty,
                'role_name' => $user->role,
                'role_id' => $role_id,
                'is_active' => (bool)$user->is_active,
                'faculty_id' => $user->faculty_id ?? null // Додано для деканів
            ];
        }

        return [
            'users' => $out,
            'pagination' => [
                'page' => (int)$page,
                'limit' => (int)$limit,
                'total' => $total,
                'totalPages' => $totalPages
            ]
        ];
    }

    static function getUserByID($user_id)
    {
        $user = \R::findOne(self::$table, 'id = ?', [$user_id]);
        if ($user) {
            // Визначення role_id на основі role_name
            $role_id = 1; // user за замовчуванням
            if ($user->role === 'admin') {
                $role_id = 2;
            } elseif ($user->role === 'dean') {
                $role_id = 3;
            }

            return [
                'id' => $user->id,
                'fio' => $user->fio,
                'email' => $user->email,
                'is_active' => intval($user->is_active) == 1,
                'specialty' => $user->specialty,
                'role_name' => $user->role,
                'role_id' => $role_id,
                'faculty_id' => $user->faculty_id ?? null // Додано для деканів
            ];
        }

        return false;
    }

    static function login($email, $password)
    {
        $user = \R::findOne(self::$table, 'email = ?', [$email]);
        if ($user == null) {
            \App\Core\Response::badRequest("User not found", "not_user");
        }
        $isValid = \App\Core\PasswordManager::verify($password, $user->password);
        if (!$isValid) {
            \App\Core\Response::badRequest("Invalid password", "invalid_password");
        }

        // Визначення role_id на основі role_name
        $role_id = 1; // user за замовчуванням
        if ($user->role === 'admin') {
            $role_id = 2;
        } elseif ($user->role === 'dean') {
            $role_id = 3;
        }

        return [
            'id' => $user->id,
            'fio' => $user->fio,
            'email' => $user->email,
            'is_active' => intval($user->is_active) == 1,
            'specialty' => $user->specialty,
            'role_name' => $user->role,
            'role_id' => $role_id,
            'faculty_id' => $user->faculty_id ?? null // Додано для деканів
        ];
    }

    static function updateProfile($user_id, $fio = null, $specialty = null, $password = null)
    {
        $user = \R::findOne(self::$table, 'id = ?', [$user_id]);
        if ($user === null) {
            return false;
        }

        // Оновлюємо тільки передані поля
        if ($fio !== null) {
            $user->fio = $fio;
        }

        if ($specialty !== null) {
            $user->specialty = $specialty;
        }

        if ($password !== null) {
            $user->password = \App\Core\PasswordManager::hash($password);
        }

        return \R::store($user);
    }

    static function register($email, $password, $fio, $specialty)
    {
        $user = \R::findOne(self::$table, 'email = ?', [$email]);
        if ($user !== null) {
            \App\Core\Response::badRequest("Email is busy", "email_busy");
        }
        $user = \R::dispense(self::$table);
        $user->email = $email;
        $user->fio = $fio;
        $user->is_active = false;
        $user->specialty = $specialty;
        $user->password = \App\Core\PasswordManager::hash($password);
        $user->role = 'user';
        return \R::store($user);
    }

}