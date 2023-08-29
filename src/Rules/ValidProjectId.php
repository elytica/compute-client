<?php
namespace Elytica\ComputeClient\Rules;

use Illuminate\Contracts\Validation\Rule;
use Elytica\ComputeClient\ComputeService;

class ValidProjectId implements Rule
{
    protected $token;

    public function __construct($token)
    {
        $this->token = $token;
    }

    public function passes($attribute, $value)
    {
        $computeService = new ComputeService($this->token);
        $projects = $computeService->getProjects();

        foreach ($projects as $project) {
            if ($project->id == $value) {
                return true;
            }
        }

        return false;
    }

    public function message()
    {
        return 'The :attribute is not a valid project ID.';
    }
}

