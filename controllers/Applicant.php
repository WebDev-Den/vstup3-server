<?php

namespace App\Controllers;
class ControllerApplicant extends Controller
{
    function query_GetPdf()
    {
        $user = \App\Core\Validate::roles(['admin', 'user', 'dean']);
        if (!$user['is_active']) {
            \App\Core\Response::forbidden('User not active');
        }
        $id = intval($this->data_uri[0]);
        if ($id <= 0) {
            \App\Core\Response::badRequest("Id empty", "invalid_data");

        }
        $type = trim($this->data_uri[1]);
        if (empty($type) || strlen($type) < 3) {
            \App\Core\Response::badRequest("Not select type", "invalid_data");

        }

        $applicant = \App\Models\Applicant::getItemByID($id);
        if (!$applicant) {
            \App\Core\Response::badRequest("User not found", "invalid_data");
        }

        // Для деканів перевіряємо чи заявка належить їх факультету
        if ($user['role_name'] === 'dean' && !empty($user['faculty_id'])) {
            if ($applicant['faculty_key'] !== $user['faculty_id']) {
                \App\Core\Response::forbidden('You can only generate PDF for applications from your faculty');
            }
        }


        $templates = DOCUMENT_ROOT_SCRIPT . '/templates/';

        $data = [];
        $temp = new \App\Services\Templates($applicant);
        switch ($type) {
            case 'contract':
                $res = $temp->getContract();
                $data = $res['data'];
                $templates .= $res['templates'];
                break;
            case 'dogovir':
                $res = $temp->getDogovir();
                $data = $res['data'];
                $templates .= $res['templates'];
                break;
            case 'hutch':
                $res = $temp->getHutch();
                $data = $res['data'];
                $templates .= $res['templates'];
                break;
            case 'military':
                $res = $temp->getMilitary();
                $data = $res['data'];
                $templates .= $res['templates'];
                break;

            default:
                \App\Core\Response::badRequest("Templates not found", "invalid_data");
                break;
        }
        if (!file_exists($templates)) {
            \App\Core\Response::badRequest("Templates not found", "invalid_data");
        }


        $dir = DOCUMENT_ROOT_SCRIPT . '/templates/output/' . date('Y/m/d/', strtotime($applicant['created_at'])) . 'user-' . $applicant['id'] . '/';

        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
        $file_out = $dir . md5($dir) . '_' . $type . '.pdf';
        if (file_exists($file_out)) {
            unlink($file_out);
        }

        $processor = new \PDFTPProc($templates);
        $processor->setData($data);
        //   dd($processor->getFields());
        $result = $processor->save($file_out);


        if ($result['success'] && file_exists($result['outputPath'])) {
            $url = str_replace(DOCUMENT_ROOT_SCRIPT, BASE_PDF_URL, $result['outputPath']);
            return ['url' => $url];
        }


        return false;
    }

    function query_DeleteRequests()
    {
        $user = \App\Core\Validate::roles(['admin', 'user', 'dean']);
        if (!$user['is_active']) {
            \App\Core\Response::forbidden('User not active');
        }

        $id = intval($this->data_uri[0]);
        if ($id <= 0) {
            \App\Core\Response::badRequest("Id empty", "invalid_data");

        }

        // Для деканів перевіряємо чи заявка належить їх факультету
        if ($user['role_name'] === 'dean' && !empty($user['faculty_id'])) {
            $existingItem = \App\Models\Applicant::getItemByID($id);
            if (!$existingItem || $existingItem['faculty_key'] !== $user['faculty_id']) {
                \App\Core\Response::forbidden('You can only delete applications from your faculty');
            }
        }

        return \App\Models\Applicant::delete($id);
    }

    function query_GetRequests()
    {
        $user = \App\Core\Validate::roles(['admin', 'user', 'dean']);
        if (!$user['is_active']) {
            \App\Core\Response::forbidden('User not active');
        }
        if (isset($this->data_uri[0])) {
            $id = intval($this->data_uri[0]);
            if ($id > 0) {
                return \App\Models\Applicant::getItemByID($id);
            }
        }


        $props = [
            'sort_by' => $this->data_query['sort_by'] ?? null,
            'sort_order' => $this->data_query['sort_order'] ?? null,
            'search' => $this->data_query['search'] ?? null,
            'status' => $this->data_query['status'] ?? null,
            'faculty' => $this->data_query['faculty'] ?? null,
            'specialty' => $this->data_query['specialty'] ?? null,
            'payment_type' => $this->data_query['payment_type'] ?? null,
        ];

        // Для деканів примусово фільтруємо тільки по їх факультету
        if ($user['role_name'] === 'dean' && !empty($user['faculty_id'])) {
            $props['faculty'] = $user['faculty_id'];
        }

        return \App\Models\Applicant::getList($this->data_query["page"] ?? 1, $this->data_query['limit'] ?? 10, $props);

    }

    function query_PutRequests()
    {
        $user = \App\Core\Validate::roles(['admin', 'user', 'dean']);
        if (!$user['is_active']) {
            \App\Core\Response::forbidden('User not active');
        }

        $id = intval($this->data_uri[0]);
        if ($id <= 0) {
            \App\Core\Response::badRequest("Id empty", "invalid_data");

        }

        // Для деканів перевіряємо чи заявка належить їх факультету
        if ($user['role_name'] === 'dean' && !empty($user['faculty_id'])) {
            $existingItem = \App\Models\Applicant::getItemByID($id);
            if (!$existingItem || $existingItem['faculty_key'] !== $user['faculty_id']) {
                \App\Core\Response::forbidden('You can only edit applications from your faculty');
            }
        }

        $data = $this->getApplicantData();;

        // Для деканів примусово встановлюємо їх факультет
        if ($user['role_name'] === 'dean' && !empty($user['faculty_id'])) {
            $data['faculty_key'] = $user['faculty_id'];
        }

        $searchCriteria = [
            'specialty_key' => $data['specialty_key'],
            'degree_key' => $data['degree_key'],
            'study_form' => $data['study_form'],
            'education_form' => $data['education_form'],
            'payment_type' => $data['payment_type'],
            'inn_code' => $data['inn_code']
        ];
        foreach ($searchCriteria as $key => $searchCriterion) {
            if (empty($searchCriterion)) {
                \App\Core\Response::badRequest("${$key} empty", "invalid_data");
            }

        }
        return [
            'id' => \App\Models\Applicant::updateData($id, $data)
        ];
    }

    function query_PostRequests()
    {
        $user = \App\Core\Validate::roles(['admin', 'user', 'dean']);
        if (!$user['is_active']) {
            \App\Core\Response::forbidden('User not active');
        }

        $data = $this->getApplicantData();;

        // Для деканів примусово встановлюємо їх факультет
        if ($user['role_name'] === 'dean' && !empty($user['faculty_id'])) {
            $data['faculty_key'] = $user['faculty_id'];
        }

        $searchCriteria = [
            'specialty_key' => $data['specialty_key'],
            'degree_key' => $data['degree_key'],
            'study_form' => $data['study_form'],
            'education_form' => $data['education_form'],
            'payment_type' => $data['payment_type'],
            'inn_code' => $data['inn_code']
        ];
        foreach ($searchCriteria as $key => $searchCriterion) {
            if (empty($searchCriterion)) {
                \App\Core\Response::badRequest("${$key} empty", "invalid_data");
            }

        }
        return [
            'id' => \App\Models\Applicant::addData($data)
        ];
    }

}
