<?php

namespace App\Models;

class Specialty
{
    public static $table = "specialties";
    public static $facultyTable = "faculties";

    /**
     * Отримати всі спеціальності факультету
     */
    static function getAllByFacultyKey($faculty_key)
    {
        $faculty = \R::findOne(self::$facultyTable, 'faculty_key = ?', [$faculty_key]);
        if (!$faculty) {
            return false;
        }

        $specialties = \R::find(self::$table, 'faculty_id = ?', [$faculty->id]);
        $result = [];

        foreach ($specialties as $specialty) {
            $result[$specialty->specialty_key] = [
                'name' => $specialty->name,
                'name_small' => $specialty->name_small,
                'specialty_code' => $specialty->specialty_code,
                'educational_program' => $specialty->educational_program,
                'specialty_name' => $specialty->specialty_name,
                'type' => json_decode($specialty->type_data, true),
                'pricing' => json_decode($specialty->pricing_data, true)
            ];
        }

        return $result;
    }

    /**
     * Отримати спеціальність за ключем
     */
    static function getByKey($faculty_key, $specialty_key)
    {
        $faculty = \R::findOne(self::$facultyTable, 'faculty_key = ?', [$faculty_key]);
        if (!$faculty) {
            return false;
        }

        $specialty = \R::findOne(self::$table, 'faculty_id = ? AND specialty_key = ?', [$faculty->id, $specialty_key]);
        if (!$specialty) {
            return false;
        }

        return [
            'name' => $specialty->name,
            'name_small' => $specialty->name_small,
            'specialty_code' => $specialty->specialty_code,
            'educational_program' => $specialty->educational_program,
            'specialty_name' => $specialty->specialty_name,
            'type' => json_decode($specialty->type_data, true),
            'pricing' => json_decode($specialty->pricing_data, true)
        ];
    }

    /**
     * Створити нову спеціальність
     */
    static function create($faculty_key, $data)
    {
        $faculty = \R::findOne(self::$facultyTable, 'faculty_key = ?', [$faculty_key]);
        if (!$faculty) {
            return false;
        }

        // Перевірка на дублікат
        $existing = \R::findOne(self::$table, 'faculty_id = ? AND specialty_key = ?', [$faculty->id, $data['specialtyId']]);
        if ($existing) {
            return false;
        }

        $specialty = \R::dispense(self::$table);
        $specialty->faculty_id = $faculty->id;
        $specialty->specialty_key = $data['specialtyId'];
        $specialty->name = $data['name'];
        $specialty->name_small = $data['name_small'] ?? null;
        $specialty->specialty_code = $data['specialty_code'];
        $specialty->educational_program = $data['educational_program'];
        $specialty->specialty_name = $data['specialty_name'] ?? null;
        $specialty->type_data = json_encode($data['type'] ?? []);
        $specialty->pricing_data = json_encode($data['pricing'] ?? []);

        return \R::store($specialty);
    }

    /**
     * Оновити спеціальність
     */
    static function update($faculty_key, $specialty_key, $data)
    {
        $faculty = \R::findOne(self::$facultyTable, 'faculty_key = ?', [$faculty_key]);
        if (!$faculty) {
            return false;
        }

        $specialty = \R::findOne(self::$table, 'faculty_id = ? AND specialty_key = ?', [$faculty->id, $specialty_key]);
        if (!$specialty) {
            return false;
        }

        // Оновлюємо тільки передані поля
        if (isset($data['name'])) $specialty->name = $data['name'];
        if (isset($data['name_small'])) $specialty->name_small = $data['name_small'];
        if (isset($data['specialty_code'])) $specialty->specialty_code = $data['specialty_code'];
        if (isset($data['educational_program'])) $specialty->educational_program = $data['educational_program'];
        if (isset($data['specialty_name'])) $specialty->specialty_name = $data['specialty_name'];
        if (isset($data['type'])) $specialty->type_data = json_encode($data['type']);
        if (isset($data['pricing'])) $specialty->pricing_data = json_encode($data['pricing']);

        return \R::store($specialty);
    }

    /**
     * Видалити спеціальність
     */
    static function delete($faculty_key, $specialty_key)
    {
        $faculty = \R::findOne(self::$facultyTable, 'faculty_key = ?', [$faculty_key]);
        if (!$faculty) {
            return false;
        }

        $specialty = \R::findOne(self::$table, 'faculty_id = ? AND specialty_key = ?', [$faculty->id, $specialty_key]);
        if (!$specialty) {
            return false;
        }

        \R::trash($specialty);
        return true;
    }
}
