<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PreviewMarketplaceWidgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'template_slug' => ['nullable', 'string', 'max:64', 'required_without:source_widget_id'],
            'source_widget_id' => ['nullable', 'integer', 'min:1', 'required_without:template_slug'],
        ];
    }
}
