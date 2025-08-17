<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use Illuminate\Http\Request;

class JobApplicationController extends Controller
{
    public function index(){
        $applications = JobApplication::orderBy('created_at', 'DESC')
                        ->with('job', 'user', 'employer')
                        ->paginate(5);
        // dd($appliations);
        return view('admin.job-applications.list',[
            'applications' => $applications
        ]);

    }

    public function destroy(Request $request){
        $id = $request->id;

        $jobApplication = JobApplication::find($id);

        if ($jobApplication == null) {
            return response()->json([
                'status' => false,
                'message' => 'Either job application deleted or not found'
            ]);
        }

        $jobApplication->delete();
        session()->flash('success', 'User deleted sucessfully');
            return response()->json([
                'status' => true,
                'message' => 'Job application deleted successfully'
            ]);
    }
}
