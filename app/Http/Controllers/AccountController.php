<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\User;
use App\Models\JobType;
use App\Models\Category;
use App\Models\JobApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Intervention\Image\ImageManager;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Drivers\Gd\Driver;



class AccountController extends Controller
{
    // This method will show ragitration Page
    public function registration()
    {
        return view('front_page.account.registration');
    }

    // This method will save register user

    public function processRegister(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:4|same:confirm_password',
            'confirm_password' => 'required'
        ]);


        if ($validator->passes()) {

            $user = new User();
            $user->name = $request->name;
            $user->email = $request->email;
            $user->password = Hash::make($request->password);
            $user->save();

            session()->flash('success', 'You have registerd successfully.');

            return response()->json([
                'status' => true,
                'errors' => []
            ]);
        } else {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ]);
        }
    }

    // This method will show login Page
    public function login()
    {
        return view('front_page.account.login');
    }

    public function authenticate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->passes()) {

            if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
                return redirect()->route('account.profile');
            } else {
                return redirect()->route('account.login')->with('error', 'Either Email or Password is incorrect');
            }
        } else {
            return redirect()->route('account.login')->withErrors($validator)->withInput($request->only('email'));
        }
    }

    public function profile()
    {
        $user_id = Auth::user()->id;
        $user = User::where('id', $user_id)->first();

        return view('front_page.account.profile', [
            'user' => $user
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user_id = Auth::user()->id;
        $validator = Validator::make($request->all(), [
            'name' => 'required|min:4|max:30',
        ]);
        $validator = Validator::make($request->all(), [
            'name' => 'required|min:4|max:30',
            'email' => 'required|email|unique:users,email,' . $user_id . ',id',
        ]);

        if ($validator->passes()) {

            $user = User::find($user_id);
            $user->name = $request->name;
            $user->email = $request->email;
            $user->designation = $request->designation;
            $user->mobile = $request->mobile;
            $user->save();

            session()->flash('success', 'Profile updated successfully.');

            return response()->json([
                'status' => true,
                'errors' => []
            ]);
        } else {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ]);
        }
    }

    public function logout()
    {
        Auth::logout();
        return redirect()->route('account.login');
    }

    public function profilePic(Request $request)
    {
        $profile_id =  Auth::user()->id;

        $validator = Validator::make($request->all(), [
            'image' => 'required|image'
        ]);

        if ($validator->passes()) {

            $image = $request->image;
            $imgExtension = $image->getClientOriginalExtension();
            $imageName = $profile_id . '_' . time() . '_' . '.' . $imgExtension;
            $image->move(public_path('/profile_img/'), $imageName);

            // Create a small size image
            $sourcePath = public_path('/profile_img/' . $imageName);
            $manager = new ImageManager(Driver::class);
            $image = $manager->read($sourcePath);

            // crop the best fitting 1:1 ratio (200x200) and resize to 200x200 pixel
            $image->cover(150, 150);
            $image->toPng()->save(public_path('/profile_img/thumb/' . $imageName));

            // Delete old picture 
            File::delete(public_path('/profile_img/' . Auth::user()->image));
            File::delete(public_path('/profile_img/thumb/' . Auth::user()->image));

            $user = User::where('id', $profile_id)->update(['image' => $imageName]);

            session()->flash('success', 'Profile picture updated Successfully.');

            return response()->json([
                'status' => true,
                'errors' => []
            ]);
        } else {

            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ]);
        }
    }

    public function createJob()
    {
        $categories = Category::where('status', 1)->orderBy('name')->get();
        $jobTypes = JobType::where('status', 1)->get();
        return view('front_page.account.job.create_job', [
            'categories' => $categories,
            'jobTypes' => $jobTypes,
        ]);
    }

    public function saveJob(Request $request)
    {
        $rules = [
            'title' => 'required|min:4|max:150',
            'category' => 'required',
            'jobType' => 'required',
            'vacancy' => 'required|integer',
            'location' => 'required|max:100',
            'description' => 'required',
            'experience' => 'required',
            'companyName' => 'required|min:3|max:100',
        ];

        $validator = validator::make($request->all(), $rules);



        if ($validator->passes()) {

            $post_job = Job::create([
                'user_id' => Auth::user()->id,
                'title' => $request->title,
                'category_id' => $request->category,
                'job_type_id' => $request->jobType,
                'vacancy' => $request->vacancy,
                'salary' => $request->salary,
                'location' => $request->location,
                'description' => $request->description,
                'benefits' => $request->benefits,
                'responsibility' => $request->responsibility,
                'qualifications' => $request->qualifications,
                'experience' => $request->experience,
                'keywords' => $request->keywords,
                'company_name' => $request->companyName,
                'company_location' => $request->company_location,
                'company_website' => $request->website,

            ]);

            session()->flash('success', 'Job created Successfully.');

            return response()->json([
                'status' => true,
                'errors' => [],
            ]);
        } else {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ]);
        }
    }

    public function myJob()
    {
        $postJobId = Job::where('user_id', Auth::user()->id)->orderByDesc('created_at')->with('jobType')->paginate(5);
        return view('front_page.account.job.my-jobs', ['postId' => $postJobId]);
    }

    public function editJob(string $id)
    {
        $categories = Category::where('status', 1)->orderBy('name')->get();
        $jobTypes = JobType::where('status', 1)->get();


        $editJob = Job::where([
            'user_id' => Auth::user()->id,
            'id' => $id,
        ])->first();

        if ($editJob == null) {
            abort(404);
        }
        return view('front_page.account.job.update_job', [
            'categories' => $categories,
            'jobTypes' => $jobTypes,
            'editJob' => $editJob
        ]);
    }

    public function updateJob(Request $request, $id)
    {
        $rules = [
            'title' => 'required|min:4|max:150',
            'category' => 'required',
            'jobType' => 'required',
            'vacancy' => 'required|integer',
            'location' => 'required|max:100',
            'description' => 'required',
            'experience' => 'required',
            'companyName' => 'required|min:3|max:100',
        ];

        $validator = validator::make($request->all(), $rules);
        $updateJob =  Job::find($id);
        if ($validator->passes()) {
            $updateJob->update([
                'user_id' => Auth::user()->id,
                'title' => $request->title,
                'category_id' => $request->category,
                'job_type_id' => $request->jobType,
                'vacancy' => $request->vacancy,
                'salary' => $request->salary,
                'location' => $request->location,
                'description' => $request->description,
                'benefits' => $request->benefits,
                'responsibility' => $request->responsibility,
                'qualifications' => $request->qualifications,
                'experience' => $request->experience,
                'keywords' => $request->keywords,
                'company_name' => $request->companyName,
                'company_location' => $request->company_location,
                'company_website' => $request->website,

            ]);

            session()->flash('success', 'Job updated Successfully.');

            return response()->json([
                'status' => true,
                'errors' => [],
            ]);
        } else {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ]);
        }
    }

    public function deleteJob(Request $request)
    {
        $job = Job::where([
           'user_id' => Auth::user()->id,
           'id' => $request->job_id
        ])->first();

        if ($job == null) {
            session()->flash('error', 'The job was not found.');
            return response()->json([
             'status' => true
            ]);
        }

        Job::where('id', $request->job_id)->delete();
        session()->flash('success', 'Job deleted Successfully.');
        return response()->json([
             'status' => true
        ]);
    }

    //***  Display Applied Job page  
  public function appliedJob()
  {
    $appliedJobs = JobApplication::where('user_id', Auth::user()->id)->with(['job', 'job.jobType', 'job.applicationsCount'])->paginate(6);
    return view('front_page.account.job.appliedJob', [
        'appliedJobs' =>  $appliedJobs
    ]);
 }

    //*** Remove Applied Job   
    public function removeJob(Request $request)
    {
        $removeJob = JobApplication::where(['id' => $request->id, 'user_id' => Auth::user()->id])->first();


        if ($removeJob == null) {
            session()->flash('error','The Job application is not found.');
            return response()->json([
               'status' => false
            ]);
        }
     
       JobApplication::find($request->id)->delete();
       session()->flash('success','The application has been removed.');
       return response()->json([
              'status' => true
        ]);
    }


}
