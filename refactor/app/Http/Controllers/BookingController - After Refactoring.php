<?php

namespace DTApi\Http\Controllers;

use DTApi\Models\Job;
use DTApi\Models\Distance;
use Illuminate\Http\Request;
use DTApi\Repository\BookingRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{
    /**
     * @var BookingRepository
     */
    protected $repository;

    /**
     * BookingController constructor.
     * @param BookingRepository $bookingRepository
     */
    public function __construct(BookingRepository $bookingRepository)
    {
        $this->repository = $bookingRepository;
    }

    public function index(Request $request)
    {
        $userId = $request->get('user_id');
        $authenticatedUser = $request->__authenticatedUser;

        if ($userId) {
            $response = $this->repository->getUsersJobs($userId);
        } elseif ($this->isAdmin($authenticatedUser)) {
            $response = $this->repository->getAll($request);
        } else {
            $response = [];
        }

        return response()->json($response);
    }

    public function show($id)
    {
        $job = $this->repository->with('translatorJobRel.user')->find($id);
        return response()->json($job);
    }

    public function store(Request $request)
    {
        $data = $request->validate([ /* Add validation rules */]);
        $response = $this->repository->store($request->__authenticatedUser, $data);
        return response()->json($response);
    }

    public function update($id, Request $request)
    {
        $data = $request->except(['_token', 'submit']);
        $cuser = $request->__authenticatedUser;
        $response = $this->repository->updateJob($id, $data, $cuser);
        return response()->json($response);
    }

    public function immediateJobEmail(Request $request)
    {
        $data = $request->all();
        $response = $this->repository->storeJobEmail($data);
        return response()->json($response);
    }

    public function getHistory(Request $request)
    {
        $userId = $request->get('user_id');
        if ($userId) {
            $response = $this->repository->getUsersJobsHistory($userId, $request);
            return response()->json($response);
        }
        return response()->json([], 404);
    }

    public function acceptJob(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;
        $response = $this->repository->acceptJob($data, $user);
        return response()->json($response);
    }

    public function acceptJobWithId(Request $request)
    {
        $jobId = $request->validate(['job_id' => 'required|integer']);
        $user = $request->__authenticatedUser;
        $response = $this->repository->acceptJobWithId($jobId, $user);
        return response()->json($response);
    }

    public function cancelJob(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;
        $response = $this->repository->cancelJobAjax($data, $user);
        return response()->json($response);
    }

    public function endJob(Request $request)
    {
        $data = $request->all();
        $response = $this->repository->endJob($data);
        return response()->json($response);
    }

    public function customerNotCall(Request $request)
    {
        $data = $request->all();
        $response = $this->repository->customerNotCall($data);
        return response()->json($response);
    }

    public function getPotentialJobs(Request $request)
    {
        $user = $request->__authenticatedUser;
        $response = $this->repository->getPotentialJobs($user);
        return response()->json($response);
    }

    public function distanceFeed(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'jobid' => 'required|integer',
            'distance' => 'nullable|string',
            'time' => 'nullable|string',
            'session_time' => 'nullable|string',
            'admincomment' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $distance = $data['distance'] ?? '';
        $time = $data['time'] ?? '';
        $jobId = $data['jobid'];
        $session = $data['session_time'] ?? '';

        $updateData = [
            'distance' => $distance,
            'time' => $time,
            'admin_comments' => $data['admincomment'] ?? '',
            'flagged' => $data['flagged'] === 'true' ? 'yes' : 'no',
            'manually_handled' => $data['manually_handled'] === 'true' ? 'yes' : 'no',
            'by_admin' => $data['by_admin'] === 'true' ? 'yes' : 'no',
            'session_time' => $session
        ];

        Distance::where('job_id', $jobId)->update(array_filter(['distance' => $distance, 'time' => $time]));
        Job::where('id', $jobId)->update(array_filter($updateData));

        return response()->json(['message' => 'Record updated!']);
    }

    public function reopen(Request $request)
    {
        $data = $request->all();
        $response = $this->repository->reopen($data);
        return response()->json($response);
    }

    public function resendNotifications(Request $request)
    {
        $data = $request->all();
        $job = $this->repository->find($data['jobid']);
        $jobData = $this->repository->jobToData($job);
        $this->repository->sendNotificationTranslator($job, $jobData, '*');
        return response()->json(['success' => 'Push sent']);
    }

    public function resendSMSNotifications(Request $request)
    {
        $data = $request->all();
        $job = $this->repository->find($data['jobid']);

        try {
            $this->repository->sendSMSNotificationToTranslator($job);
            return response()->json(['success' => 'SMS sent']);
        } catch (\Exception $e) {
            Log::error('SMS failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function isAdmin($user)
    {
        $adminRoles = [env('ADMIN_ROLE_ID'), env('SUPERADMIN_ROLE_ID')];
        return in_array($user->user_type, $adminRoles);
    }
}
