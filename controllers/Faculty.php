<?php

namespace App\Controllers;

class ControllerFaculty extends Controller
{
    /**
     * GET /faculty/getAll
     * Отримання всіх факультетів з їх спеціальностями
     */
    function query_GetGetall()
    {
        $user = \App\Core\Validate::roles(['admin', 'dean']);
        if (!$user['is_active']) {
            \App\Core\Response::forbidden('User not active');
        }

        // Для деканів повертаємо тільки їх факультет
        if ($user['role_name'] === 'dean' && !empty($user['faculty_id'])) {
            $faculty = \App\Models\Faculty::getByKey($user['faculty_id']);
            if (!$faculty) {
                \App\Core\Response::notFound('Faculty not found');
            }
            return [$user['faculty_id'] => $faculty];
        }

        // Для адмінів - всі факультети
        return \App\Models\Faculty::getAll();
    }

    /**
     * GET /faculty/getById/{facultyId}
     * Отримання факультету за ID
     */
    function query_GetGetbyid()
    {
        $user = \App\Core\Validate::roles(['admin', 'dean']);
        if (!$user['is_active']) {
            \App\Core\Response::forbidden('User not active');
        }

        $facultyId = $this->data_uri[0] ?? null;
        if (empty($facultyId)) {
            \App\Core\Response::badRequest("Faculty ID is required", "invalid_data");
        }

        // Для деканів перевіряємо доступ
        if ($user['role_name'] === 'dean' && $user['faculty_id'] !== $facultyId) {
            \App\Core\Response::forbidden('You can only access your own faculty');
        }

        $faculty = \App\Models\Faculty::getByKey($facultyId);
        if (!$faculty) {
            \App\Core\Response::notFound('Faculty not found');
        }

        return $faculty;
    }

    /**
     * POST /faculty/create
     * Створення нового факультету (тільки адміністратори)
     */
    function query_PostCreate()
    {
        $user = \App\Core\Validate::roles('admin');
        if (!$user['is_active']) {
            \App\Core\Response::forbidden('User not active');
        }

        $facultyId = $this->data_query['facultyId'] ?? null;
        $name = $this->data_query['name'] ?? null;
        $name_min = $this->data_query['name_min'] ?? null;

        if (empty($facultyId) || empty($name) || empty($name_min)) {
            \App\Core\Response::badRequest("All fields are required", "invalid_data");
        }

        $result = \App\Models\Faculty::create($facultyId, $name, $name_min);
        if (!$result) {
            \App\Core\Response::conflict('Faculty with this ID already exists');
        }

        return [
            'facultyId' => $facultyId,
            'name' => $name,
            'name_min' => $name_min
        ];
    }

    /**
     * PUT /faculty/update/{facultyId}
     * Оновлення факультету
     */
    function query_PutUpdate()
    {
        $user = \App\Core\Validate::roles(['admin', 'dean']);
        if (!$user['is_active']) {
            \App\Core\Response::forbidden('User not active');
        }

        $facultyId = $this->data_uri[0] ?? null;
        if (empty($facultyId)) {
            \App\Core\Response::badRequest("Faculty ID is required", "invalid_data");
        }

        // Для деканів перевіряємо доступ
        if ($user['role_name'] === 'dean' && $user['faculty_id'] !== $facultyId) {
            \App\Core\Response::forbidden('You can only update your own faculty');
        }

        $name = $this->data_query['name'] ?? null;
        $name_min = $this->data_query['name_min'] ?? null;

        $result = \App\Models\Faculty::update($facultyId, $name, $name_min);
        if (!$result) {
            \App\Core\Response::notFound('Faculty not found');
        }

        return \App\Models\Faculty::getByKey($facultyId);
    }

    /**
     * DELETE /faculty/delete/{facultyId}
     * Видалення факультету (тільки адміністратори)
     */
    function query_DeleteDelete()
    {
        $user = \App\Core\Validate::roles('admin');
        if (!$user['is_active']) {
            \App\Core\Response::forbidden('User not active');
        }

        $facultyId = $this->data_uri[0] ?? null;
        if (empty($facultyId)) {
            \App\Core\Response::badRequest("Faculty ID is required", "invalid_data");
        }

        $result = \App\Models\Faculty::delete($facultyId);
        if (!$result) {
            \App\Core\Response::notFound('Faculty not found');
        }

        return true;
    }
}
