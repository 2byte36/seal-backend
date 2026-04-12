<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:1000'],
            'attachment' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.after_or_equal' => 'Leave start date must be today or a future date.',
            'end_date.after_or_equal' => 'Leave end date must be on or after the start date.',
            'attachment.max' => 'Attachment file size must not exceed 5MB.',
            'attachment.mimes' => 'Attachment must be a PDF, JPG, PNG, DOC, or DOCX file.',
        ];
    }
}
