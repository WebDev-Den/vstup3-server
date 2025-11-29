<?php


namespace App\Models;
class Applicant
{
    public static $table = "applicant";

    static function delete($id)
    {
        $item = \R::findOne(self::$table, 'id = ?', [$id]);
        if (!$item) return false;
        \R::trash($item);
        return true;
    }

    static function getItemByID($id)
    {
        $item = \R::findOne(self::$table, 'id = ?', [$id]);

        if (!$item) {
            return false;
        }

        // Десеріалізуємо поля якщо вони є
        $militaryEducationInstitutions = [];
        if ($item->military_education_institutions) {
            $unserialized = @unserialize($item->military_education_institutions);
            $militaryEducationInstitutions = is_array($unserialized) ? $unserialized : [];
        }

        $militaryFamilyComposition = [];
        if ($item->military_family_composition) {
            $unserialized = @unserialize($item->military_family_composition);
            $militaryFamilyComposition = is_array($unserialized) ? $unserialized : [];
        }
        $required_files = [];
        if ($item->required_files) {
            $unserialized = @unserialize($item->required_files);
            $required_files = is_array($unserialized) ? $unserialized : [];
        }

        $out = [
            // === МЕТАДАНІ ЗАЯВИ ===
            'id' => $item->id,
            'status' => $item->status,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at ?? null, // додано поле якого немає в таблиці

            // === ОСВІТНІ ДАНІ ===
            'faculty_key' => $item->faculty_key,
            'specialty_key' => $item->specialty_key,
            'degree_key' => $item->degree_key,
            'study_form' => $item->study_form,
            'education_form' => $item->education_form,
            'payment_type' => $item->payment_type,

            // === ДАНІ АБІТУРІЄНТА ===
            'last_name' => $item->last_name,
            'first_name' => $item->first_name,
            'patronymic' => $item->patronymic,
            'phone' => $item->phone,
            'service_recipient' => $item->service_recipient,

            // === ПАСПОРТНІ ДАНІ ===
            'passport_type' => $item->passport_type,
            'passport_series' => $item->passport_series,
            'passport_number' => $item->passport_number,
            'passport_issue_date' => $item->passport_issue_date,
            'passport_birth_date' => $item->passport_birth_date,
            'passport_issued_by' => $item->passport_issued_by,
            'unsd_code' => $item->unsd_code,
            'inn_code' => $item->inn_code,
            'registration_address' => $item->registration_address,
            'residence_address' => $item->residence_address,
            'address_same_as_registration' => (bool)$item->address_same_as_registration,

            // === ДАНІ КОНТАКТНОЇ ОСОБИ ===
            'contact_represents_self' => (bool)$item->contact_represents_self,
            'contact_relationship' => $item->contact_relationship,
            'contact_last_name' => $item->contact_last_name,
            'contact_first_name' => $item->contact_first_name,
            'contact_patronymic' => $item->contact_patronymic,
            'contact_phone' => $item->contact_phone,
            'contact_passport_type' => $item->contact_passport_type,
            'contact_passport_series' => $item->contact_passport_series,
            'contact_passport_number' => $item->contact_passport_number,
            'contact_passport_issue_date' => $item->contact_passport_issue_date,
            'contact_passport_birth_date' => $item->contact_passport_birth_date,
            'contact_passport_issued_by' => $item->contact_passport_issued_by,
            'contact_registration_address' => $item->contact_registration_address,
            'contact_inn_code' => $item->contact_inn_code,

            // === ДАНІ ЗАМОВНИКА (для контрактної форми) ===
            'customer_type' => $item->customer_type,
            'customer_relationship' => $item->customer_relationship,
            'customer_last_name' => $item->customer_last_name,
            'customer_first_name' => $item->customer_first_name,
            'customer_patronymic' => $item->customer_patronymic,
            'customer_phone' => $item->customer_phone,
            'customer_passport_type' => $item->customer_passport_type,
            'customer_passport_series' => $item->customer_passport_series,
            'customer_passport_number' => $item->customer_passport_number,
            'customer_passport_issue_date' => $item->customer_passport_issue_date,
            'customer_passport_birth_date' => $item->customer_passport_birth_date,
            'customer_passport_issued_by' => $item->customer_passport_issued_by,
            'customer_registration_address' => $item->customer_registration_address,
            'customer_inn_code' => $item->customer_inn_code,

            // === ВІЙСЬКОВІ ДАНІ ===
            'military_registration_required' => (bool)$item->military_registration_required,
            'military_gender' => $item->military_gender,
            'military_marital_status' => $item->military_marital_status,
            'military_education_level' => $item->military_education_level,
            'military_citizenship' => $item->military_citizenship,
            'military_accounting_group' => $item->military_accounting_group,
            'military_accounting_category' => $item->military_accounting_category,
            'military_accounting_composition' => $item->military_accounting_composition,
            'military_rank' => $item->military_rank,
            'military_specialty_number' => $item->military_specialty_number,
            'military_suitability' => $item->military_suitability,
            'military_district_registration' => $item->military_district_registration,
            'military_district_accommodation' => $item->military_district_accommodation,

            // === ОСВІТНІ УСТАНОВИ (масив) ===
            'military_education_institutions' => $militaryEducationInstitutions,

            // === СКЛАД СІМ'Ї (масив) ===
            'military_family_composition' => $militaryFamilyComposition,
            'required_files' => $required_files,
        ];

        return $out;
    }


    static function getList($page, $limit, $props = [])
    {
        $offset = ($page - 1) * $limit;

        // Налаштування сортування
        $allowedOrderBy = ['id', 'created_at', 'last_name', 'first_name', 'status'];
        $allowedSortOrder = ['ASC', 'DESC'];

        $orderBy = in_array($props['sort_by'] ?? '', $allowedOrderBy) ? $props['sort_by'] : 'id';
        $sortOrder = in_array(strtoupper($props['sort_order'] ?? ''), $allowedSortOrder) ?
            strtoupper($props['sort_order']) : 'ASC';

        // Побудова SQL запиту
        $whereClause = '';
        $params = [];

        // Додавання пошуку
        if (!empty($props['search'])) {
            $searchTerm = '%' . trim($props['search']) . '%';
            $whereClause = 'WHERE (
            last_name LIKE ? OR 
            first_name LIKE ? OR 
            patronymic LIKE ? OR 
            inn_code LIKE ? OR 
            passport_number LIKE ? OR 
            phone LIKE ? OR 
            CONCAT(last_name, " ", first_name, " ", patronymic) LIKE ?
        )';

            // Додаємо параметр пошуку 7 разів для кожного поля
            $params = array_fill(0, 7, $searchTerm);
        }

        // Додавання інших фільтрів
        $additionalFilters = [];

        if (!empty($props['status'])) {
            $additionalFilters[] = 'status = ?';
            $params[] = $props['status'];
        }

        if (!empty($props['faculty'])) {
            $additionalFilters[] = 'faculty_key = ?';
            $params[] = $props['faculty'];
        }

        if (!empty($props['specialty'])) {
            $additionalFilters[] = 'specialty_key = ?';
            $params[] = $props['specialty'];
        }

        if (!empty($props['payment_type'])) {
            $additionalFilters[] = 'payment_type = ?';
            $params[] = $props['payment_type'];
        }

        // Об'єднання фільтрів
        if (!empty($additionalFilters)) {
            if (!empty($whereClause)) {
                $whereClause .= ' AND ' . implode(' AND ', $additionalFilters);
            } else {
                $whereClause = 'WHERE ' . implode(' AND ', $additionalFilters);
            }
        }

        // Підрахунок загальної кількості з фільтрами
        $countSql = $whereClause ?: '';
        $total = \R::count(self::$table, $countSql, $params);
        $totalPages = ceil($total / $limit);

        // Основний запит з пагінацією
        $sql = "{$whereClause} ORDER BY {$orderBy} {$sortOrder} LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $items = \R::findAll(self::$table, $sql, $params);

        $out = [];
        foreach ($items as $item) {

            $required_files = [];
            if ($item->required_files) {
                $unserialized = @unserialize($item->required_files);
                $required_files = is_array($unserialized) ? $unserialized : [];
            }


            $out[] = [
                "id" => $item->id,
                "status" => $item->status,
                "last_name" => $item->last_name,
                "first_name" => $item->first_name,
                "patronymic" => $item->patronymic,
                "phone" => $item->phone,
                "specialty_key" => $item->specialty_key,
                "faculty_key" => $item->faculty_key,
                "degree_key" => $item->degree_key,
                "payment_type" => $item->payment_type,
                "study_form" => $item->study_form,
                "education_form" => $item->education_form,
                "created_at" => $item->created_at,
                "updated_at" => $item->updated_at,
                "required_files" => $required_files,
            ];
        }

        return [
            'requests' => $out,
            'pagination' => [
                'currentPage' => (int)$page,
                'itemsPerPage' => (int)$limit,
                'totalItems' => $total,
                'totalPages' => $totalPages
            ]
        ];
    }

    public static function updateData($id, $data)
    {
        $record = \R::findOne(self::$table, 'id = ?', [$id]);
        if ($record == null) return false;

        foreach ($data as $key => $value) {
            $record->$key = $value;
        }
        $record->updated_at = date('Y-m-d H:i:s');
        return \R::store($record);
    }

    public static function addData($data)
    {
        $searchCriteria = [
            'specialty_key' => $data['specialty_key'],
            'degree_key' => $data['degree_key'],
            'study_form' => $data['study_form'],
            'education_form' => $data['education_form'],
            'payment_type' => $data['payment_type'],
            'inn_code' => $data['inn_code']
        ];

        $record = \R::findOrCreate(self::$table, $searchCriteria);

        foreach ($data as $key => $value) {
            $record->$key = $value;
        }
        $record->created_at = date('Y-m-d H:i:s');
        $record->updated_at = date('Y-m-d H:i:s');
        return \R::store($record);

    }

}