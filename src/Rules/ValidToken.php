<?php
namespace Elytica\ComputeClient\Rules;

use Illuminate\Contracts\Validation\Rule;
use Elytica\ComputeClient\ComputeService;

class ValidToken implements Rule
{
    protected string $baseUrl;

    public function __construct(?string $baseUrl = null)
    {
        $this->baseUrl = $baseUrl ?? config('compute.base_url', 'https://service.elytica.com');
    }

    public function passes($attribute, $value)
    {
        try {
            new ComputeService($value, $this->baseUrl);
            return true;
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    public function message()
    {
        return 'The :attribute is not a valid token.';
    }
}
