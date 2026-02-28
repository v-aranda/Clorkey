<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'link_type' => 'required|string|in:citation,reference,embed',
            'source_type' => 'required|string|in:App\\Models\\LibraryFile,App\\Models\\Document',
            'source_id' => 'required|integer',
            'source_section' => 'nullable|string',
            'source_meta' => 'nullable|array',
            'position' => 'nullable|integer',
        ];
    }
}
