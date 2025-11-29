<?php

namespace App\Controllers;
class Controller
{
    public $data_query = [];
    public $data_uri;

    public function __construct($data)
    {
        $this->data_uri = $data['uri'];
        $this->data_query = $data['query'];

    }

    public function getApplicantData()
    {
        return [
            // Освітні дані
            'status' => $this->data_query['status'] ?? 'draft',
            'faculty_key' => $this->data_query['faculty_key'] ?? null,
            'specialty_key' => $this->data_query['specialty_key'] ?? null,
            'degree_key' => $this->data_query['degree_key'] ?? null,
            'study_form' => $this->data_query['study_form'] ?? 'full',
            'education_form' => $this->data_query['education_form'] ?? 'inPerson',
            'payment_type' => $this->data_query['payment_type'] ?? 'contract',

            // Дані абітурієнта
            'last_name' => $this->data_query['last_name'] ?? null,
            'first_name' => $this->data_query['first_name'] ?? null,
            'patronymic' => $this->data_query['patronymic'] ?? null,
            'phone' => $this->data_query['phone'] ?? null,
            'service_recipient' => $this->data_query['service_recipient'] ?? null,

            // Паспортні дані
            'passport_type' => $this->data_query['passport_type'] ?? null,
            'passport_series' => $this->data_query['passport_series'] ?? '',
            'passport_number' => $this->data_query['passport_number'] ?? null,
            'passport_issue_date' => $this->data_query['passport_issue_date'] ?? null,
            'passport_birth_date' => $this->data_query['passport_birth_date'] ?? null,
            'passport_issued_by' => $this->data_query['passport_issued_by'] ?? null,
            'unsd_code' => $this->data_query['unsd_code'] ?? null,
            'inn_code' => $this->data_query['inn_code'] ?? null,
            'registration_address' => $this->data_query['registration_address'] ?? null,
            'residence_address' => $this->data_query['residence_address'] ?? null,
            'address_same_as_registration' => $this->data_query['address_same_as_registration'] ?? false,

            // Дані контактної особи
            'contact_represents_self' => $this->data_query['contact_represents_self'] ?? false,
            'contact_relationship' => $this->data_query['contact_relationship'] ?? null,
            'contact_last_name' => $this->data_query['contact_last_name'] ?? null,
            'contact_first_name' => $this->data_query['contact_first_name'] ?? null,
            'contact_patronymic' => $this->data_query['contact_patronymic'] ?? null,
            'contact_phone' => $this->data_query['contact_phone'] ?? null,
            'contact_passport_type' => $this->data_query['contact_passport_type'] ?? null,
            'contact_passport_series' => $this->data_query['contact_passport_series'] ?? '',
            'contact_passport_number' => $this->data_query['contact_passport_number'] ?? null,
            'contact_passport_issue_date' => $this->data_query['contact_passport_issue_date'] ?? null,
            'contact_passport_birth_date' => $this->data_query['contact_passport_birth_date'] ?? null,
            'contact_passport_issued_by' => $this->data_query['contact_passport_issued_by'] ?? null,
            'contact_registration_address' => $this->data_query['contact_registration_address'] ?? null,
            'contact_inn_code' => $this->data_query['contact_inn_code'] ?? null,

            // Дані замовника (тільки для контрактної форми)
            'customer_type' => $this->data_query['customer_type'] ?? null,
            'customer_relationship' => $this->data_query['customer_relationship'] ?? null,
            'customer_last_name' => $this->data_query['customer_last_name'] ?? null,
            'customer_first_name' => $this->data_query['customer_first_name'] ?? null,
            'customer_patronymic' => $this->data_query['customer_patronymic'] ?? null,
            'customer_phone' => $this->data_query['customer_phone'] ?? null,
            'customer_inn_code' => $this->data_query['customer_inn_code'] ?? null,
            'customer_passport_type' => $this->data_query['customer_passport_type'] ?? null,
            'customer_passport_series' => $this->data_query['customer_passport_series'] ?? '',
            'customer_passport_number' => $this->data_query['customer_passport_number'] ?? null,
            'customer_passport_issue_date' => $this->data_query['customer_passport_issue_date'] ?? null,
            'customer_passport_birth_date' => $this->data_query['customer_passport_birth_date'] ?? null,
            'customer_passport_issued_by' => $this->data_query['customer_passport_issued_by'] ?? null,
            'customer_registration_address' => $this->data_query['customer_registration_address'] ?? null,

            // Військові дані
            'military_registration_required' => $this->data_query['military_registration_required'] ?? false,
            'military_gender' => $this->data_query['military_gender'] ?? null,
            'military_marital_status' => $this->data_query['military_marital_status'] ?? null,
            'military_education_level' => $this->data_query['military_education_level'] ?? null,
            'military_citizenship' => $this->data_query['military_citizenship'] ?? null,
            'military_accounting_group' => $this->data_query['military_accounting_group'] ?? '',
            'military_accounting_category' => $this->data_query['military_accounting_category'] ?? '',
            'military_accounting_composition' => $this->data_query['military_accounting_composition'] ?? '',
            'military_rank' => $this->data_query['military_rank'] ?? '',
            'military_specialty_number' => $this->data_query['military_specialty_number'] ?? '',
            'military_suitability' => $this->data_query['military_suitability'] ?? '',
            'military_district_registration' => $this->data_query['military_district_registration'] ?? '',
            'military_district_accommodation' => $this->data_query['military_district_accommodation'] ?? '',

            // Освітні установи (масив)
            'military_education_institutions' => serialize($this->data_query['military_education_institutions'] ?? []),

            // Склад сім'ї (масив)
            'military_family_composition' => serialize($this->data_query['military_family_composition'] ?? []),
            'required_files' => serialize($this->data_query['required_files'] ?? []),
        ];
    }

    public function getTokens($user)
    {

        $user['code'] = \App\Core\PasswordManager::hash(\App\Core\Validate::getClientIP());
        global $JWT;
        $token = $JWT->generateToken($user);
        $refreshPayload = [
            "token" => $token,
            "id" => $user['id'],
            'code' => $user['code'],
        ];
        $refreshToken = $JWT->generateToken($refreshPayload, JWT_TIME_REFRESH);
        \App\Models\JWT::addTokens($user['id'], $token, $refreshToken, time() + JWT_TIME_REFRESH);
        setcookie('refresh_token', $refreshToken, time() + JWT_TIME_REFRESH, '/', '', true, true);
        return [
            "access_token" => $token,
            "refresh_token" => $refreshToken,
        ];
    }
}
