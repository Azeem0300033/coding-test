<?php

namespace DTApi\Repository;

use DTApi\Models\Company;
use DTApi\Models\Department;
use DTApi\Models\Type;
use DTApi\Models\UsersBlacklist;
use Illuminate\Support\Facades\Log;
use Monolog\Logger;
use DTApi\Models\User;
use DTApi\Models\Town;
use DTApi\Models\UserMeta;
use DTApi\Models\UserTowns;
use DTApi\Events\JobWasCreated;
use DTApi\Models\UserLanguages;
use Monolog\Handler\StreamHandler;
use Illuminate\Support\Facades\DB;
use Monolog\Handler\FirePHPHandler;

/**
 * Class BookingRepository
 * @package DTApi\Repository
 */
class UserRepository extends BaseRepository
{

    protected $model;
    protected $logger;

    /**
     * @param User $model
     */
    function __construct(User $model)
    {
        parent::__construct($model);
//        $this->mailer = $mailer;
        $this->logger = new Logger('admin_logger');

        $this->logger->pushHandler(new StreamHandler(storage_path('logs/admin/laravel-' . date('Y-m-d') . '.log'), Logger::DEBUG));
        $this->logger->pushHandler(new FirePHPHandler());
    }

    public function createOrUpdate($id = null, $request)
    {
        $model = is_null($id) ? new User : User::findOrFail($id);
        $model->user_type = $request->role;
        $model->name = $request->name;
        $model->company_id = $request->company_id != '' ? $request->company_id : 0;
        $model->department_id = $request->department_id != '' ? $request->department_id : 0;
        $model->email = $request->email;
        $model->dob_or_orgid = $request->dob_or_orgid;
        $model->phone = $request->phone;
        $model->mobile = $request->mobile;


        if ($request->has('password')) {
            $model->password = bcrypt($request->password);
        }

        $model->detachAllRoles();
        $model->save();
        $model->attachRole($request['role']);
        $data = array();

        if ($request['role'] == env('CUSTOMER_ROLE_ID')) {

            if ($request['consumer_type'] == 'paid') {
                if ($request['company_id'] == '') {
                    $type = Type::where('code', 'paid')->first();
                    $company = Company::create(['name' => $request['name'], 'type_id' => $type->id, 'additional_info' => 'Created automatically for user ' . $model->id]);
                    $department = Department::create(['name' => $request['name'], 'company_id' => $company->id, 'additional_info' => 'Created automatically for user ' . $model->id]);

                    $model->company_id = $company->id;
                    $model->department_id = $department->id;
                    $model->save();
                }
            }

            $this->userMeta($model->id, 'consumer_type', $request['consumer_type']);

            $blacklistUpdated = [];
            $userBlacklist = UsersBlacklist::where('user_id', $id)->get();
            $userTranslId = collect($userBlacklist)->pluck('translator_id')->all();

            $diff = null;
            if ($request['translator_ex']) {
                $diff = array_intersect($userTranslId, $request['translator_ex']);
            }
            if ($diff || $request['translator_ex']) {
                foreach ($request['translator_ex'] as $translatorId) {
                    $blacklist = new UsersBlacklist();
                    if ($model->id) {
                        $already_exist = UsersBlacklist::translatorExist($model->id, $translatorId);
                        if ($already_exist == 0) {
                            $blacklist->user_id = $model->id;
                            $blacklist->translator_id = $translatorId;
                            $blacklist->save();
                        }
                        $blacklistUpdated [] = $translatorId;
                    }

                }
                if ($blacklistUpdated) {
                    UsersBlacklist::deleteFromBlacklist($model->id, $blacklistUpdated);
                }
            } else {
                UsersBlacklist::where('user_id', $model->id)->delete();
            }


        } else if ($request['role'] == env('TRANSLATOR_ROLE_ID')) {

            $this->userMeta($model->id, 'translator_type', $request['translator_type']);

            $data['translator_type'] = $request['translator_type'];
            $data['worked_for'] = $request['worked_for'];
            if ($request['worked_for'] == 'yes') {
                $data['organization_number'] = $request['organization_number'];
            }
            $data['gender'] = $request['gender'];
            $data['translator_level'] = $request['translator_level'];

            $langidUpdated = [];
            if ($request['user_language']) {
                foreach ($request['user_language'] as $langId) {
                    $userLang = new UserLanguages();
                    $already_exit = $userLang::langExist($model->id, $langId);
                    if ($already_exit == 0) {
                        $userLang->user_id = $model->id;
                        $userLang->lang_id = $langId;
                        $userLang->save();
                    }
                    $langidUpdated[] = $langId;

                }
                if ($langidUpdated) {
                    $userLang::deleteLang($model->id, $langidUpdated);
                }
            }

        }

        if ($request['new_towns']) {

            $towns = new Town;
            $towns->townname = $request['new_towns'];
            $towns->save();
            $newTownsId = $towns->id;
        }

        $townidUpdated = [];
        if ($request['user_towns_projects']) {
            $del = DB::table('user_towns')->where('user_id', '=', $model->id)->delete();
            foreach ($request['user_towns_projects'] as $townId) {
                $userTown = new UserTowns();
                $already_exit = $userTown::townExist($model->id, $townId);
                if ($already_exit == 0) {
                    $userTown->user_id = $model->id;
                    $userTown->town_id = $townId;
                    $userTown->save();
                }
                $townidUpdated[] = $townId;

            }
        }

        if ($request['status'] == '1') {
            $this->enable($model->id);
        } else {
            $this->disable($model->id);
        }
        return $model;
    }

    public function userMeta($user_id, $key, $value)
    {
        $user_meta = UserMeta::firstOrCreate(['user_id' => $user_id]);
        $user_meta->$key = $value;
        $user_meta->save();
    }

    public function enable($id)
    {
        $user = User::findOrFail($id);
        $user->status = '1';
        $user->save();

    }

    public function disable($id)
    {
        $user = User::findOrFail($id);
        $user->status = '0';
        $user->save();

    }

    public function getTranslators()
    {
        return User::where('user_type', 2)->get();
    }

}