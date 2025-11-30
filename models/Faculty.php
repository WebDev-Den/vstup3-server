<?php

namespace App\Models;

class Faculty
{
    public static $table = "faculties";
    public static $specialtyTable = "specialties";

    /**
     * Отримати всі факультети з їх спеціальностями
     */
    static function getAll()
    {
        $faculties = \R::findAll(self::$table, 'ORDER BY faculty_key');
        $result = [];

        foreach ($faculties as $faculty) {
            $result[$faculty->faculty_key] = [
                'name' => $faculty->name,
                'name_min' => $faculty->name_min,
                'specialty' => self::getSpecialtiesForFaculty($faculty->id)
            ];
        }

        return $result;
    }

    /**
     * Отримати факультет за ключем
     */
    static function getByKey($faculty_key)
    {
        $faculty = \R::findOne(self::$table, 'faculty_key = ?', [$faculty_key]);
        if (!$faculty) {
            return false;
        }

        return [
            'name' => $faculty->name,
            'name_min' => $faculty->name_min,
            'specialty' => self::getSpecialtiesForFaculty($faculty->id)
        ];
    }

    /**
     * Створити новий факультет
     */
    static function create($faculty_key, $name, $name_min)
    {
        // Перевірка на дублікат
        $existing = \R::findOne(self::$table, 'faculty_key = ?', [$faculty_key]);
        if ($existing) {
            return false;
        }

        $faculty = \R::dispense(self::$table);
        $faculty->faculty_key = $faculty_key;
        $faculty->name = $name;
        $faculty->name_min = $name_min;

        return \R::store($faculty);
    }

    /**
     * Оновити факультет
     */
    static function update($faculty_key, $name = null, $name_min = null)
    {
        $faculty = \R::findOne(self::$table, 'faculty_key = ?', [$faculty_key]);
        if (!$faculty) {
            return false;
        }

        if ($name !== null) {
            $faculty->name = $name;
        }

        if ($name_min !== null) {
            $faculty->name_min = $name_min;
        }

        return \R::store($faculty);
    }

    /**
     * Видалити факультет (CASCADE видалить всі спеціальності)
     */
    static function delete($faculty_key)
    {
        $faculty = \R::findOne(self::$table, 'faculty_key = ?', [$faculty_key]);
        if (!$faculty) {
            return false;
        }

        \R::trash($faculty);
        return true;
    }

    /**
     * Отримати спеціальності для факультету
     */
    private static function getSpecialtiesForFaculty($faculty_id)
    {
        $specialties = \R::find(self::$specialtyTable, 'faculty_id = ?', [$faculty_id]);
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
}
