# coding-test
 
Hi,
Azeem Here.

Also done the Test Refactoring. In the test code, bad practice is that manage all functionality in the one function in this case we need to make multiple functions.

Refactored Code.
Explanation
It is always a good idea to perform the refactoring. By using Laravel features such as Accessors, Repositories, Scope, Traits, Mutators, we can make code more optimized and efficient.

Code:
$allJobs = Job::query();

if (isset($requestdata['feedback']) && $requestdata['feedback'] != 'false') {
	$allJobs->where('ignore_feedback', '0');
        $allJobs->whereHas('feedback', function ($q) {
               $q->where('rating', '<=', '3');
        });
}

Refactored:
$allJobs = Job::query();
$allJobs->when($requestdata['feedback'] != 'false', function ($query) {
	$query->where('ignore_feedback', '0');
        return $query->whereHas('feedback', function ($q) {
                 $q->where('rating', '<=', '3');
        });
});

Code:
$cuser = User::find($user_id);

Refactored:
$cuser = User::find($user_id);
if(!isset($cuser) || empty($cuser)) {
	abort(404)
}

Code:
if ($cuser && $cuser->is('customer')) { }

Refactored:
if ($cuser->is('customer')) { }

Code:
if ($cuser && $cuser->is('customer')) {
	$jobs = $cuser->jobs()->with('user.userMeta', 'user.average', 'translatorJobRel.user.average', 'language', 'feedback')->whereIn('status', ['pending', 'assigned', 'started'])->orderBy('due', 'asc')->get();
        $usertype = 'customer';
} elseif ($cuser && $cuser->is('translator')) {
        $jobs = Job::getTranslatorJobs($cuser->id, 'new');
        $jobs = $jobs->pluck('jobs')->all();
        $usertype = 'translator';
}

Refactored:
$usertype = $this->getUserType($cuser);
$jobs = $this->getJobs($cuser);

public function getUserType($cuser)
{
	if ($cuser->is('customer')) 
	{							
            $usertype = 'customer';
	} elseif ($cuser->is('translator')) {
	    $usertype = 'translator';
	} else {
	    $usertype = "";
	}
		
	return $usertype;
}
	
public function getJobs($cuser)
{
	if ($cuser->is('customer')) 
	{
            $jobs = $cuser->jobs()
			->with([
				'user.userMeta', 
				'user.average', 
				'translatorJobRel.user.average', 
				'language', 
				'feedback'
			])
			->whereIn('status', ['pending', 'assigned', 'started'])
			->orderBy('due', 'asc')
			->get();
							
	} elseif($cuser->is('translator')) {
			
		$jobs = Job::getTranslatorJobs($cuser->id, 'new');
            	$jobs = $jobs->pluck('jobs')->all();
			
	} else {
			
		$jobs = NULL;
	}
		
	return $jobs;
}

Code:
$job->user_email = @$data['user_email'];

Refactored:
if(isset($data['user_email'])) {
	$job->user_email = $data['user_email'];
} else {
	$job->user_email = "";
}

Code:
$this->mailer->send($email, $name, $subject, 'emails.job-created', $send_data);

Refactored: 
try {
     $this->mailer->send($email, $name, $subject, 'emails.job-created', $send_data);
} catch(\Exception $e) {
     $e->getMessage();	
}

Code:
$job_type = 'unpaid';
if ($translator_type == 'professional')
   $job_type = 'paid';   /*show all jobs for professionals.*/
else if ($translator_type == 'rwstranslator')
   $job_type = 'rws';  /* for rwstranslator only show rws jobs. */
else if ($translator_type == 'volunteer')
   $job_type = 'unpaid';  /* for volunteers only show unpaid jobs. */

Refactored:
switch($translator_type) {
	case 'professional': 
		return $job_type = 'paid';
		break;
	case 'rwstranslator': 
		return $job_type = 'rws';
		break;
	case 'volunteer': 
		return $job_type = 'unpaid';
		break;
	default
		return $job_type = 'unpaid';
}

Code: 
$response = curl_exec($ch);

Refactored:
try {
    $response = curl_exec($ch);
} catch(\Exception $e) {
    $e->getMessage();
}


-------------------------------------------------------------------
