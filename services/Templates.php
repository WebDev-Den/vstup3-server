<?php

namespace App\Services;
class Templates
{
    public function __construct($userData)
    {
        $this->user = $userData;
        $faculty = [];
        $file = '/var/www/vstup_2/src/data/faculty.json';
        if (file_exists($file)) {
            $faculty = file_get_contents($file);
            $this->faculty = json_decode($faculty, true);
        }


        $this->pay = [
            'government' => 'Навчання за державне фінансування',
            'contract' => 'коштів фізичних та/або юридичних осіб'
        ];

        $this->contacts_types = [
            'mother' => 'Мати',
            'father' => 'Батько',
            'brother' => 'Брат',
            'setsra' => 'Сетсра',
            'grandpa' => 'Дідусь',
            'grandma' => 'Бабуся',
            'guardian' => 'Опікун',
            'other' => 'Інше'
        ];

        $this->gender = [
            'male' => 'Чоловік',
            'female' => 'Жінка'
        ];

        $this->marital_status = [
            'not_married' => 'не перебуває в шлюбі',
            'married' => 'перебуває в шлюбі',
            'other' => 'інше'
        ];

        $this->education = [
            'basic_general_secondary' => 'Базова загальна середня',
            'complete_general_secondary' => 'Повна загальна середня',
            'vocational_technical' => 'Професійно-технічна',
            'incomplete_higher' => 'Неповна вища',
            'basic_higher' => 'Базова вища',
            'complete_higher' => 'Повна вища'
        ];

    }

    function getMilitary()
    {
        $shortener = new \App\Core\AddressShortener();
        $data = [
            'data' => date('d.m.Y'),
            'inn' => $this->user['inn_code'],
            'sex' => $this->gender[$this->user['military_gender']] ?? '',
            'f_name' => $this->user['last_name'],
            'name' => $this->user['first_name'],
            'last_name' => $this->user['patronymic'],
            'bd_data' => date('d', strtotime($this->user['passport_birth_date'])),
            'bd_mount' => date('m', strtotime($this->user['passport_birth_date'])),
            'bd_year' => date('Y', strtotime($this->user['passport_birth_date'])),
            'nationality' => $this->user['military_citizenship'],
            'edu_name_1' => '',
            'edu_number_1' => '',
            'edu_year_1' => '',
            'edu_name_2' => '',
            'edu_number_2' => '',
            'edu_year_2' => '',
            'edu_name_3' => '',
            'edu_number_3' => '',
            'edu_year_3' => '',
            'family_connection_1' => '',
            'family_pib_1' => '',
            'family_year_1' => '',
            'family_connection_2' => '',
            'family_pib_2' => '',
            'family_year_2' => '',
            'family_connection_3' => '',
            'family_pib_3' => '',
            'family_year_3' => '',
            'pas_seria' => $this->user['passport_series'],
            'pas_num' => $this->user['passport_number'],
            'pass_vidav' => $this->user['passport_issued_by'],
            'pas_data' => date('d.m.Y', strtotime($this->user['passport_issue_date'])),
            'accounting_group' => $this->user['military_accounting_group'],
            'cat_obl' => $this->user['military_accounting_category'],
            'accounting_composition' => $this->user['military_accounting_composition'],
            'military_transport' => $this->user['military_rank'],
            'voz' => $this->user['military_specialty_number'],
            'reg_vidil' => $this->user['military_district_registration'],
            'live_vidil' => $this->user['military_district_accommodation'],
            'group_mistse_prozhyvannya_1753959678244' => $shortener->shortenAddress($this->user['registration_address']),
            'group_mistse_reyestratsiyi_1753959741126' => $shortener->shortenAddress($this->user['residence_address']),
            'phone' => $this->user['phone'],
            'marital_status' => $this->marital_status[$this->user['military_marital_status']] ?? '',
            'confirm' => ''
        ];

        $key = 1;
        foreach ($this->user['military_education_institutions'] as $military_education_institution) {
            if (empty($military_education_institution['institution_name'])) continue;

            $data['edu_name_' . $key] = $military_education_institution['institution_name'];
            $data['edu_number_' . $key] = $military_education_institution['document_series_number'];
            $data['edu_year_' . $key] = $military_education_institution['issue_date'] . ' р.';
            $key++;
        }

        $key = 1;
        foreach ($this->user['military_family_composition'] as $military_education_institution) {
            if (empty($military_education_institution['relationship_type'])) continue;

            $data['family_connection_' . $key] = $this->contacts_types[$military_education_institution['relationship_type']];
            $data['family_pib_' . $key] = $military_education_institution['full_name'];
            $data['family_year_' . $key] = $military_education_institution['birth_date'] . ' р.';
            $key++;
        }

        return [
            'templates' => 'military.pdftp',
            'data' => $data
        ];
    }

    function getHutch()
    {
        $shortener = new \App\Core\AddressShortener();
        $data = [
            'faculty' => $this->faculty[$this->user['faculty_key']]['name_min'],
            'pib' => implode(' ', [$this->user['last_name'], $this->user['first_name'], $this->user['patronymic']]),
            'phone' => $this->user['phone'],
            'pass_number_data' => implode(' ', [$this->user['passport_series'], $this->user['passport_number']]) . ' , ' . date('d.m.Y', strtotime($this->user['passport_issue_date'])),
            'pass_vidiv' => $this->user['passport_issued_by'],
            'inn' => $this->user['inn_code'],
            "group_adresa_1753899455146" => $shortener->shortenAddress($this->user['registration_address'])
        ];

        if (strlen($data['pass_vidiv']) < 8) {
            $data['pass_number_data'] .= ' , ' . $data['pass_vidiv'];
            $data['pass_vidiv'] = '';
        }

        return [
            'templates' => 'zajava_gurtozhitok.pdftp',
            'data' => $data
        ];
    }

    function getDogovir()
    {
        $shortener = new \App\Core\AddressShortener();

        // Формування ПІБ абітурієнта
        $entrant_pib = implode(' ', [
            $this->user['last_name'],
            $this->user['first_name'],
            $this->user['patronymic']
        ]);

        // Формування ПІБ представника (якщо є)
        $representative_pib = '';
        if (!empty($this->user['contact_last_name']) && !$this->user['contact_represents_self']) {
            $representative_pib = implode(' ', [
                $this->user['contact_last_name'],
                $this->user['contact_first_name'],
                $this->user['contact_patronymic']
            ]);
        }


        $faculty = $this->faculty[$this->user['faculty_key']]['specialty'];
        $specialty = $faculty[$this->user['specialty_key']];
        $degree = $specialty['type'][$this->user['degree_key']];

        $credits = intval($degree["credits"]);
        if ($this->user['study_form'] == 'shortened') {
            $credits = $credits - ($credits / 4);
        }

        $data = [
            // === ЗВИЧАЙНІ ПОЛЯ (без групування) ===

            // Сторінка 1 - Основна інформація
            'pib_entrant' => $entrant_pib,
            'representative_pib' => $representative_pib,
            'curs_start' => $this->user['study_form'] == 'full' ? 1 : 2,
            'form_education' => $this->user['education_form'] == "inPerson" ? ($this->user['study_form'] == 'full' ? 'денна' : 'скорочена') : 'заочна',
            'program_name' => $specialty['educational_program'],
            'program_code_name' => $specialty['name'],
            'specialization' => '',
            'education_degree' => $degree['name'] ?? '',
            'accreditation' => ($degree['accreditation']["status"] ? '' : "не") . ' акредитованою',
            'accreditation_data' => $degree['accreditation']["date"],
            'credits' => $credits, // стандартно для бакалавра

            // Сторінка 2 - Документи абітурієнта
            'pass_seria' => $this->user['passport_series'],
            'pass_num' => $this->user['passport_number'],
            'inn_entrant' => $this->user['inn_code'],
            'phone_entrant' => $this->user['phone'],

            // Документи представника
            'pib_representative' => $representative_pib,
            'pass_seria_representative' => $this->user['contact_passport_series'] ?? '',
            'pass_num_representative' => $this->user['contact_passport_number'] ?? '',
            'inn_representative' => $this->user['contact_inn_code'] ?? '', // немає в даних контактної особи

            // Додаткові поля
            'phone_representative' => $this->user['contact_phone'] ?? '',

            // === ГРУПОВІ ПОЛЯ - тексти для автоматичного розбиття ===

            // Група: Паспорт абітурієнта
            'group_abituriyetn_pasport_1753996632677' => sprintf(
                '%s,  %sр.',
                $this->user['passport_issued_by'],
                date('d.m.Y', strtotime($this->user['passport_issue_date'])),

            ),

            // Група: Реєстрація абітурієнта
            'group_reyestratsiya_abituriyent_1753998361873' => $shortener->shortenAddress($this->user['registration_address']),

            // Група: Паспорт представника
            'group_pred_pasport_1753998807619' => !empty($this->user['contact_passport_issue_date']) ?
                sprintf(
                    '%s, %sр.',

                    $this->user['contact_passport_issued_by'] ?? '', date('d.m.Y', strtotime($this->user['contact_passport_issue_date']))
                ) : '',

            // Група: Реєстрація представника
            'group_pred_rehistr_1753998817364' => !empty($this->user['contact_registration_address']) ?
                sprintf(
                    '%s. %s.',
                    $shortener->shortenAddress($this->user['contact_registration_address']),
                    $this->user['contact_phone'] ?? ''
                ) : '',

            // Група: Фінансування
            'group_finansuvannya_1753999273088' => $this->pay[$this->user['payment_type']] ?? ''
        ];

        return [
            'templates' => 'dogovir.pdftp',
            'data' => $data
        ];
    }

    function getContract()
    {
        // Формування ПІБ абітурієнта
        $entrant_pib = implode(' ', [
            $this->user['last_name'],
            $this->user['first_name'],
            $this->user['patronymic']
        ]);

        // Формування ПІБ замовника
        $customer_pib = '';
        if (!empty($this->user['customer_last_name'])) {
            $customer_pib = implode(' ', [
                $this->user['customer_last_name'],
                $this->user['customer_first_name'],
                $this->user['customer_patronymic']
            ]);
        }

        $shortener = new \App\Core\AddressShortener();


        $faculty = $this->faculty[$this->user['faculty_key']]['specialty'];
        $specialty = $faculty[$this->user['specialty_key']];
        $degree = $specialty['type'][$this->user['degree_key']];


        $data = [
            // Дані для сторінки 1
            'caster_pib' => $customer_pib,
            'pib_to_order' => $this->user['service_recipient'] ?? '',


            'price_name_int' => '',
            'price_year_1' => '',
            'price_year_2' => '',
            'price_year_3' => '',
            'price_year_4' => '',

            // Дані заявника (caster) для сторінки 2
            'caster_pass_seria' => $this->user['customer_passport_series'] ?? '',
            'caster_pass_num' => $this->user['customer_passport_number'] ?? '',
            'caster_vudan_data' => implode(', ', [$this->user['customer_passport_issued_by'], date('d.m.Y', strtotime($this->user['customer_passport_issue_date']))]),
            // 'caster_reg_addres' => $shortener->shortenAddress($this->user['customer_registration_address']) ?? '',
            'group_reyestr_zamov_1754064253180' => $shortener->shortenAddress($this->user['customer_registration_address']) ?? '',
            'caster_inn' => $this->user['customer_inn_code'] ?? '',
            'caster_phone' => $this->user['customer_phone'] ?? '',

            // Дані абітурієнта (abit) для сторінки 2
            'abit_pib' => $entrant_pib,
            'abit_pass_seria' => $this->user['passport_series'],
            'abit_pass_num' => $this->user['passport_number'],
            'abit_vudan_data' => implode(', ', [$this->user['passport_issued_by'], date('d.m.Y', strtotime($this->user['passport_issue_date']))]),
            //  'abit_reg' => $shortener->shortenAddress($this->user['registration_address']),
            'group_reyestr_abir_1754064246562' => $shortener->shortenAddress($this->user['registration_address']),
            'abit_inn' => $this->user['inn_code'],
            'abit_phone' => $this->user['phone']
        ];


        $pricing = [];
        if (isset($specialty['pricing'][$this->user['degree_key']][$this->user['education_form']])) {
            $pricing = $specialty['pricing'][$this->user['degree_key']][$this->user['education_form']];
        }
        $data['price_year_1'] = $pricing['y1'] ?? 0;
        $data['price_year_2'] = $pricing['y2'] ?? 0;
        $data['price_year_3'] = $pricing['y3'] ?? 0;
        $data['price_year_4'] = $pricing['y4'] ?? 0;
        if ($this->user['study_form'] == 'shortened') {

            $data['price_year_4'] = 0;

        }
        $data['price_name_int'] = floatval($data['price_year_1']) + floatval($data['price_year_2']) + floatval($data['price_year_3']) + floatval($data['price_year_4']);

        $converter = new \App\Core\NumberToWordsUkrainian();

        $data['price_year_1'] = empty($data['price_year_1']) ? '' : number_format($data['price_year_1'], 2, ',', ' ');
        $data['price_year_2'] = empty($data['price_year_2']) ? '' : number_format($data['price_year_2'], 2, ',', ' ');
        $data['price_year_3'] = empty($data['price_year_3']) ? '' : number_format($data['price_year_3'], 2, ',', ' ');
        $data['price_year_4'] = empty($data['price_year_4']) ? '' : number_format($data['price_year_4'], 2, ',', ' ');

        $text = $converter->convertMoney($data['price_name_int']);

        $data['price_name_int'] = number_format($data['price_name_int'], 2, ',', ' ') . " ({$text})";
        return [
            'templates' => 'contract.pdftp',
            'data' => $data
        ];
    }

}