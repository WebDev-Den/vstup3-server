<?php

namespace App\Controllers;

class ControllerSpecialty extends Controller
{
    /**
     * GET /specialty/getAll/{facultyId}
     * Отримання всіх спеціальностей факультету
     */
    function query_GetGetall()
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
            \App\Core\Response::forbidden('You can only access specialties of your own faculty');
        }

        $specialties = \App\Models\Specialty::getAllByFacultyKey($facultyId);
        if ($specialties === false) {
            \App\Core\Response::notFound('Faculty not found');
        }

        return $specialties;
    }

    /**
     * GET /specialty/getById/{facultyId}/{specialtyId}
     * Отримання спеціальності за ID
     */
    function query_GetGetbyid()
    {
        $user = \App\Core\Validate::roles(['admin', 'dean']);
        if (!$user['is_active']) {
            \App\Core\Response::forbidden('User not active');
        }

        $facultyId = $this->data_uri[0] ?? null;
        $specialtyId = $this->data_uri[1] ?? null;

        if (empty($facultyId) || empty($specialtyId)) {
            \App\Core\Response::badRequest("Faculty ID and Specialty ID are required", "invalid_data");
        }

        // Для деканів перевіряємо доступ
        if ($user['role_name'] === 'dean' && $user['faculty_id'] !== $facultyId) {
            \App\Core\Response::forbidden('You can only access specialties of your own faculty');
        }

        $specialty = \App\Models\Specialty::getByKey($facultyId, $specialtyId);
        if (!$specialty) {
            \App\Core\Response::notFound('Specialty not found');
        }

        return $specialty;
    }

    /**
     * POST /specialty/create/{facultyId}
     * Створення нової спеціальності
     */
    function query_PostCreate()
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
            \App\Core\Response::forbidden('You can only create specialties for your own faculty');
        }

        // Валідація обов'язкових полів
        $required = ['specialtyId', 'name', 'specialty_code', 'educational_program'];
        foreach ($required as $field) {
            if (empty($this->data_query[$field])) {
                \App\Core\Response::badRequest("Field '{$field}' is required", "invalid_data");
            }
        }

        $result = \App\Models\Specialty::create($facultyId, $this->data_query);
        if (!$result) {
            \App\Core\Response::conflict('Specialty with this ID already exists or faculty not found');
        }

        return [
            'facultyId' => $facultyId,
            'specialtyId' => $this->data_query['specialtyId']
        ];
    }

    /**
     * PUT /specialty/update/{facultyId}/{specialtyId}
     * Оновлення спеціальності
     */
    function query_PutUpdate()
    {
        $user = \App\Core\Validate::roles(['admin', 'dean']);
        if (!$user['is_active']) {
            \App\Core\Response::forbidden('User not active');
        }

        $facultyId = $this->data_uri[0] ?? null;
        $specialtyId = $this->data_uri[1] ?? null;

        if (empty($facultyId) || empty($specialtyId)) {
            \App\Core\Response::badRequest("Faculty ID and Specialty ID are required", "invalid_data");
        }

        // Для деканів перевіряємо доступ
        if ($user['role_name'] === 'dean' && $user['faculty_id'] !== $facultyId) {
            \App\Core\Response::forbidden('You can only update specialties of your own faculty');
        }

        $result = \App\Models\Specialty::update($facultyId, $specialtyId, $this->data_query);
        if (!$result) {
            \App\Core\Response::notFound('Specialty not found');
        }

        return \App\Models\Specialty::getByKey($facultyId, $specialtyId);
    }

    /**
     * DELETE /specialty/delete/{facultyId}/{specialtyId}
     * Видалення спеціальності
     */
    function query_DeleteDelete()
    {
        $user = \App\Core\Validate::roles(['admin', 'dean']);
        if (!$user['is_active']) {
            \App\Core\Response::forbidden('User not active');
        }

        $facultyId = $this->data_uri[0] ?? null;
        $specialtyId = $this->data_uri[1] ?? null;

        if (empty($facultyId) || empty($specialtyId)) {
            \App\Core\Response::badRequest("Faculty ID and Specialty ID are required", "invalid_data");
        }

        // Для деканів перевіряємо доступ
        if ($user['role_name'] === 'dean' && $user['faculty_id'] !== $facultyId) {
            \App\Core\Response::forbidden('You can only delete specialties from your own faculty');
        }

        $result = \App\Models\Specialty::delete($facultyId, $specialtyId);
        if (!$result) {
            \App\Core\Response::notFound('Specialty not found');
        }

        return true;
    }
}
