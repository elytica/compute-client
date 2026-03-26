<?php
namespace Elytica\ComputeClient\Rules;

use Illuminate\Contracts\Validation\Rule;
use Elytica\ComputeClient\ComputeService;

class ValidProjectId implements Rule
{
    protected string $token;
    protected string $baseUrl;

    public function __construct(string $token, ?string $baseUrl = null)
    {
        $this->token   = $token;
        $this->baseUrl = $baseUrl ?? config('compute.base_url', 'https://service.elytica.com');
    }

    public function passes($attribute, $value)
    {
        try {
            $computeService = new ComputeService($this->token, $this->baseUrl);
            $projects = $computeService->getProjects();

            foreach ($projects as $project) {
                if ($project->id == $value) {
                    return true;
                }
            }
        } catch (\RuntimeException $e) {
            return false;
        }

        return false;
    }

    public function message()
    {
        return 'The :attribute is not a valid project ID.';
    }
}
