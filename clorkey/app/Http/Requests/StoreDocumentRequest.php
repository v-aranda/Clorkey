<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'library_book_id' => 'required|exists:library_books,id',
            'library_folder_id' => 'nullable|exists:library_folders,id',
        ];
    }
}
