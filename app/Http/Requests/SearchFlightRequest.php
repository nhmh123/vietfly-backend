<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SearchFlightRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'trip_type' => ['required', 'string', 'in:one-way,round-trip'],
            'origin' => ['required', 'string', 'size:3'],
            'destination' => ['required', 'string', 'size:3', 'different:origin'],
            'departure_date' => ['required', 'date', 'after_or_equal:today'],
            'return_date' => ['nullable', 'date', 'after_or_equal:departure_date'],

            'adults' => ['required', 'integer', 'min:1', 'max:9'],
            'children' => ['nullable', 'integer', 'min:0'],
            'infants' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'trip_type.required' => 'Vui lòng chọn loại hình chuyến đi.',
            'trip_type.in' => 'Loại hình chuyến đi không hợp lệ.',

            'origin.required' => 'Vui lòng nhập sân bay xuất phát.',
            'origin.size' => 'Mã sân bay phải gồm đúng 3 ký tự.',

            'destination.required' => 'Vui lòng nhập sân bay đến.',
            'destination.size' => 'Mã sân bay phải gồm đúng 3 ký tự.',
            'destination.different' => 'Sân bay đến không được trùng với sân bay đi.',

            'departure_date.required' => 'Vui lòng chọn ngày khởi hành.',
            'departure_date.after_or_equal' => 'Ngày khởi hành không được là ngày trong quá khứ.',

            'return_date.after_or_equal' => 'Ngày về phải sau hoặc trùng với ngày khởi hành.',

            'adults.required' => 'Vui lòng nhập số lượng người lớn.',
            'adults.min' => 'Số lượng người lớn tối thiểu là 1.',
            'adults.max' => 'Tối đa 9 hành khách.',

            'children.min' => 'Số lượng trẻ em không thể là số âm.',
            'infants.min' => 'Số lượng em bé không thể là số âm.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {

            if ($this->infants > $this->adults) {
                $validator->errors()->add(
                    'infants',
                    'Mỗi người lớn chỉ được đi kèm tối đa 1 em bé.'
                );
            }

            if ($this->trip_type === 'one-way' && $this->return_date) {
                $validator->errors()->add(
                    'return_date',
                    'Ngày về không được phép có đối với chuyến đi một chiều.'
                );
            }

            if ($this->trip_type === 'round-trip' && !$this->return_date) {
                $validator->errors()->add(
                    'return_date',
                    'Ngày về là bắt buộc đối với chuyến đi khứ hồi.'
                );
            }
        });
    }
}
