<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use LSNepomuceno\LaravelA1PdfSign\Exceptions\ProcessRunTimeException;
use LSNepomuceno\LaravelA1PdfSign\Sign\ManageCert;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class SignPdfRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'callback' => 'nullable|url|active_url',
            'pdf' => 'required|file|mimes:pdf|max:10240',
            'identifier' => 'required|string',
            'employees' => 'nullable|array',
            'employees.*.field' => 'required|string',
            'employees.*.page' => 'required|integer|min:1',
            'employees.*.coordinates' => 'required|string|regex:/^(\d+,\s*\d+,\s*\d+,\s*\d+)$/',
            'employees.*.reason' => 'nullable|string|max:255',
            'employees.*.location' => 'nullable|string|max:255',
            'employees.*.yml' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    if ($value === null) {
                        return;
                    }

                    try {
                        Yaml::parse($value);
                    } catch (ParseException) {
                        return $fail("The $attribute field invalid must be a valid YAML string.");
                    }
                },
            ],
            'employees.*.uid' => [
                'required',
                'string',
                'size:8',
                'exists:employees,uid',
                function ($attribute, $value, $fail) {
                    $employee = Employee::where('uid', $value)->first();

                    if ($employee === null) {
                        return;
                    }

                    if (! $employee->signature?->specimen || ! $employee->signature?->certificate || ! $employee->signature?->password) {
                        return $fail('The employee is not configured for signing.');
                    }
                },
            ],
            'signatures' => 'nullable|array',
            'signatures.*.field' => 'required|string',
            'signatures.*.page' => 'required|integer|min:1',
            'signatures.*.coordinates' => 'required|string|regex:/^(\d+,\s*\d+,\s*\d+,\s*\d+)$/',
            'signatures.*.reason' => 'nullable|string|max:255',
            'signatures.*.location' => 'nullable|string|max:255',
            'signatures.*.contact' => 'nullable|string|max:255',
            'signatures.*.specimen' => 'required|file|mimes:jpg,png|max:10240',
            'signatures.*.certificate.*' => 'required|file|mimetypes:application/x-pkcs12|max:128',
            'signatures.*.yml' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    if ($value === null) {
                        return;
                    }

                    try {
                        Yaml::parse($value);
                    } catch (ParseException) {
                        return $fail("The $attribute field invalid must be a valid YAML string.");
                    }
                },
            ],
            'signatures.*.password' => [
                'required',
                'string',
                function ($attribute, $value, $fail, $validator) {
                    $index = explode('.', $attribute)[1];

                    $certificate = @$validator->getData()['signatures'][$index]['certificate'];

                    if ($certificate === null) {
                        return;
                    }

                    try {
                        (new ManageCert)->fromUpload($certificate, $value);

                        return;
                    } catch (ProcessRunTimeException $exception) {
                        if (str($exception->getMessage())->contains('password')) {
                            return $fail('The password is incorrect.');
                        }

                        throw $exception;
                    }
                },
            ],
        ];
    }
}
