<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\ChangeApplicationStatus;
use App\Exceptions\ResourceConflict;
use App\Http\Controllers\Controller;
use App\Http\JsonApi\ApplicantResource;
use App\Http\JsonApi\ApplicationResource;
use App\Http\JsonApi\JsonApiResponse;
use App\Http\Requests\UpdateApplicationStatusRequest;
use Illuminate\Http\JsonResponse;

final class ApplicationStatusController extends Controller
{
    public function __construct(private readonly ChangeApplicationStatus $changeStatus)
    {
    }

    /** PATCH /api/v1/applications/{applicationId} */
    public function __invoke(UpdateApplicationStatusRequest $request): JsonResponse
    {
        if ($request->resourceType() !== ApplicationResource::TYPE) {
            throw new ResourceConflict(sprintf('Expected resource type "%s".', ApplicationResource::TYPE));
        }

        if ($request->resourceId() !== $request->applicationId()) {
            throw new ResourceConflict('The resource id in the body does not match the URL.');
        }

        $application = ($this->changeStatus)($request->applicationId(), $request->partnerId(), $request->status());

        return JsonApiResponse::make([
            'data' => ApplicationResource::toArray($application),
            'included' => [ApplicantResource::toArray($application->applicant())],
        ]);
    }
}
