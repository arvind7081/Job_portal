<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\User;
use App\Models\JobType;
use App\Models\Category;
use Illuminate\Http\Request;
use App\Models\JobApplication;
use App\Mail\JobNotificationEmail;
use App\Models\SavedJob;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class JobsController extends Controller
{
    // This method will show jobs page
    public function index(Request $request){

        $categories = Category::where('status',1)->get();
        $jobTypes = JobType::where('status',1)->get();


        $jobs = Job::where('status', 1);

        // Serach using Keyword
        if (!empty($request->keyword)) {
            $jobs = $jobs->where(function($query) use ($request){
                $query->orWhere('title', 'like', '%'.$request->keyword. '%');
                $query->orWhere('keywords', 'like', '%'.$request->keyword. '%');
            });

            // // IInd way to write upper code
            // $jobs = $jobs->orWhere('title', 'like', '%'.$request->keyword. '%');
            // $jobs = $jobs->orWhere('keywords', 'like', '%'.$request->keyword. '%');
        }

        // Search Using location
        if (!empty($request->location)) {
            $jobs = $jobs->where('location',$request->location);
        }

        // Search Using category
        if (!empty($request->category)) {
            $jobs = $jobs->where('category_id',$request->category);
        }

        $jobTypeArray = [];
        // Search Using JobType
        if (!empty($request->jobType)) {

           $jobTypeArray = explode(',',$request->jobType);

            $jobs = $jobs->whereIn('job_type_id',$jobTypeArray);
        }


        // Search Using experience
        if (!empty($request->experience)) {
            $jobs = $jobs->where('experience',$request->experience);
        }




        $jobs = $jobs->with('jobType');

        if ($request->sort == '0') {
            $jobs = $jobs->orderBy('created_at', 'ASC');
        }else{
            $jobs = $jobs->orderBy('created_at', 'DESC');
        }

        $jobs = $jobs->paginate(9);

        return view('front.jobs', [
            'categories' => $categories,
            'jobTypes' => $jobTypes,
            'jobs' => $jobs,
            'jobTypeArray' => $jobTypeArray
        ]);
    }

    // This method will show job detail page
    public function detail($id){

        $job = Job::where([
                            'id' => $id,
                             'status' => 1
                            ])->with(['jobType', 'category'])->first();

        if ($job == null){
            abort(404);
        }

        $count = 0;
        if (Auth::user()) {
           $count = SavedJob::where([
            'user_id' => Auth::user()->id,
            'job_id' => $id
            ])->count(); 
        }

        //  Fetech applicants

        $applications = JobApplication::where('job_id', $id)->with('user')->get();

        // dd($application);

        return view('front.jobDetail', ['job' => $job, 'count' => $count, 'applications' => $applications]);
    }

    public function applyJob(Request $request){
        $id = $request->id;

        $job = Job::where('id', $id)->first();

        // If job is not found in db
        if ($job == null) {
            session()->flash('error', 'Job does not exist');
            return response()->json([
                'status' =>false,
                'message' =>'Job does not exist'
            ]);
        }

        // You can not apply on your own job
        $employer_id = $job->user_id;

        if($employer_id == Auth::user()->id){
            session()->flash('error', 'You can not apply on your own job');
            return response()->json([
                'status' =>false,
                'message' =>'You can not apply on your own job'
            ]);
        }

        // You can not apply on a job twise
        $jobApplicationCount = JobApplication::where([
            'user_id' => Auth::user()->id,
            'job_id' => $id
        ])->count();

        if($jobApplicationCount > 0){
            session()->flash('error', 'You already applied this job');
            return response()->json([
                'status' =>false,
                'message' =>'You already applied this job'
            ]);
        }

        $application = new JobApplication();
        $application->job_id = $id;
        $application->user_id = Auth::user()->id;
        $application->employer_id = $employer_id;
        $application->applied_date = now();
        $application->save();

        // Send Notification Email to Employer
        $employer = User::where('id', $employer_id)->first();
        $mailData = [
            'employer' => $employer,
            'user' => Auth::user(),
            'job' => $job,
        ]; 
        Mail::to($employer->email)->send(new JobNotificationEmail($mailData));

        $message = 'You have successfully applied.';

        session()->flash('success', $message);

            return response()->json([
                'status' =>true,
                'message' => $message
            ]);
    }
    

    public function saveJob(Request $request){
        $id = $request->id;

        $job = JOb::find($id);

        if ($job == null) {
            // session()->flash('error', "Job not found");
            return response()->json([
                'status' => false,
                'message' => "Job not found"
            ]);
        }

        // Check if user already saved the job
        $count = SavedJob::where([
            'user_id' => Auth::user()->id,
            'job_id' => $id
        ])->count();

        if ($count > 0) {
            // session()->flash('error', "You already save on  this job.");
            return response()->json([
                'status' => false,
                'message' => "You already saved this job."
            ]);
        }

        $savedJob = new SavedJob;
        $savedJob->job_id = $id;
        $savedJob->user_id = Auth::user()->id;
        $savedJob->save();

        //  session()->flash('success', "You have successfully save the job.");
        return response()->json([
            'status' => true,
            'message' => "You have successfully saved the job."
        ]);

    }    
}
