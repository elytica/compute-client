<?php 
namespace Elytica\ComputeClient\Rules;

use Illuminate\Contracts\Validation\Rule;
use Elytica\ComputeClient\ComputeService;

class ValidToken implements Rule
{
    public function passes($attribute, $value)
    {
        $computeService = new ComputeService($value);
        $user = $computeService->whoami();
        return $user !== null;
    }

    public function message()
    {
        return 'The :attribute is not a valid token.';
    }
}

