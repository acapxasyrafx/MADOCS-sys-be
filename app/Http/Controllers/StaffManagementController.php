<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\StaffManagement;
use Validator;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\ScreenAccessRoles;
use Exception;

class StaffManagementController extends Controller
{

    public function getStaffList()
    {
        $userList = DB::table('staff_management')
        ->select('staff_management.staff_id','staff_management.name','staff_management.nric_no','staff_management.contact_no','users.email','users.status as status','roles.role_name as role','users.id as user_id')
        ->leftJoin('users','users.staff_id','=','staff_management.staff_id')
        ->leftJoin('Roles','roles.id','=','users.role_id')
        ->orderBy('staff_management.name','asc')
        ->get();

        foreach($userList as $item){
        
            $item->name  =  strtoupper($item->name) ?? '-';
            $item->nric_no = $item->nric_no ?? '-';
            $item->contact_no = $item->contact_no ?? '-';
            $item->email = $item->email ?? '-';
            $item->role = strtoupper($item->role) ?? '-';
            
            if($item->status == 0){
                $item->status = 'Active'; 
            }
            if($item->status == 1){
                $item->status = 'Inactive'; 
            }
            
        }
        return response()->json(["message" => "Staff List", 'list' => $userList, "code" => 200]);
    }
    public function getStaffListbyCode($code)
    {
        $userList = DB::table('staff_management')
        ->select('staff_management.staff_id','staff_management.name','staff_management.nric_no','staff_management.contact_no','users.email','users.status as status','roles.role_name as role')
        ->leftJoin('users','users.staff_id','=','staff_management.staff_id')
        ->leftJoin('Roles','roles.id','=','users.role_id')
        ->where('roles.code',$code)
        ->orderBy('staff_management.name','asc')
        ->get();
        
        foreach($userList as $item){
        
            $item->name  =  strtoupper($item->name) ?? '-';
            $item->nric_no = $item->nric_no ?? '-';
            $item->contact_no = $item->contact_no ?? '-';
            $item->email = $item->email ?? '-';
            $item->role = strtoupper($item->role) ?? '-';
            
            if($item->status == 0){
                $item->status = 'Active'; 
            }
            if($item->status == 1){
                $item->status = 'Inactive'; 
            }
            
        }
       
        return response()->json(["message" => "Staff List by code :", 'list' => $userList, "code" => 200]);
    }
    public function getStaffListbyId(Request $request)
    {
        $userList = DB::table('staff_management')
        ->select('staff_management.staff_id','staff_management.name','staff_management.nric_no','staff_management.contact_no','users.email','users.status as status','roles.id as role_id','roles.role_name as role')
        ->leftJoin('users','users.staff_id','=','staff_management.staff_id')
        ->leftJoin('Roles','roles.id','=','users.role_id')
        ->where('staff_management.staff_id',$request->staff_id)
        ->first();
        
       
        return response()->json(["message" => "Staff List by ID :", 'list' => $userList, "code" => 200]);
    }
    public function createNewStaff(Request $request)
    {
        $dataStaff = [
            'name' => $request->name,
            'nric_no' => $request->nric_no,
            'contact_no' => $request->contact_no,
          
        ];

        if($request->editId ==''){
            $validator = Validator::make($request->all(), [
                'name' => 'required',
                'nric_no' => 'required',
                'email' => 'required|string|unique:users',
                'contact_no' => 'required',
                'role_id' => 'required',
                'added_by' => 'required',
            ]);
            if ($validator->fails()) { return response()->json(["message" => $validator->errors(), "code" => 422]); }

          //1.create staff 
          $createStaff = StaffManagement::create($dataStaff);

          $dataUser = [
            'staff_id' => $createStaff->getKey(),
            'email' => $request->email,
            'role_id' => $request->role_id,
            'status' => $request->status,
            'password' => bcrypt($request->email)
        ];
          //2. create user
          $createUser = User::create($dataUser);

          //3. create default Role Access Page
          $defaultRoleAccess = DB::table('default_role_access')
          ->select('default_role_access.id as role_id', 'screens.id as screen_id', 'screens.sub_module_id as sub_module_id', 'screens.module_id as module_id')
          ->join('screens', 'screens.id', '=', 'default_role_access.screen_id')
          ->where('default_role_access.role_id', $request->role_id)
          ->get();

            if ($defaultRoleAccess) {
                foreach ($defaultRoleAccess as $key) {
                    $screen = [
                        'module_id' => $key->module_id,
                        'sub_module_id' => $key->sub_module_id,
                        'screen_id' => $key->screen_id,
                        'staff_id' => $createUser->getKey(),
                        'access_screen' => '1',

                    ];

                    if (ScreenAccessRoles::where($screen)->count() == 0) {
                        $screen['added_by'] = $request->added_by;
                        ScreenAccessRoles::Create($screen);
                    }
                }

                return response()->json(["message" => "Record Successfully Created", "code" => 200]);
            }

        }else{ // update users
            
            $validator = Validator::make($request->all(), [
                'name' => 'required',
                'nric_no' => 'required',
                'email' => 'required|string',
                'contact_no' => 'required',
                'role_id' => 'required',
                'added_by' => 'required',
            ]);
            if ($validator->fails()) { return response()->json(["message" => $validator->errors(), "code" => 422]); }
            // edit existing record
           
            $updateStaff = StaffManagement::where('staff_id',$request->editId)->update($dataStaff); 
            $dataUpdateUser = [
                'email' => $request->email,
                'role_id' => $request->role_id,
                'status' => $request->status,
            ];
            $res = User::where('staff_id',$request->editId)->update($dataUpdateUser);
            $getUserID = User::select('id')->where('staff_id',$request->editId)->first();
            $deleteAccessScreen = DB::table('screen_access_roles')->where('staff_id',$getUserID->id)
            ->delete(); // staff id in this table refer to id in users.

            
            $defaultRoleAccess = DB::table('default_role_access')
            ->select('default_role_access.id as role_id', 'screens.id as screen_id', 'screens.sub_module_id as sub_module_id', 'screens.module_id as module_id')
            ->join('screens', 'screens.id', '=', 'default_role_access.screen_id')
            ->where('default_role_access.role_id', $request->role_id)
            ->get();
            
              if ($defaultRoleAccess) {

                  foreach ($defaultRoleAccess as $key) {
                      $screen = [
                          'module_id' => $key->module_id,
                          'sub_module_id' => $key->sub_module_id,
                          'screen_id' => $key->screen_id,
                          'staff_id' => $getUserID->id ,// ni ambik id dari table user
                          'access_screen' => '1',
  
                      ];
  
                      if (ScreenAccessRoles::where($screen)->count() == 0) {
                          $screen['added_by'] = $request->added_by;
                          ScreenAccessRoles::Create($screen);
                      }
                  }
                  return response()->json(["message" => "Record Successfully Updated", "code" => 200]);
              }
            
          
        }

      

    }
    public function isExistNric(Request $request)
    {
        $check = StaffManagement::where('nric_no', $request->nric_no)->count();
        if ($check == 0) {
            return response()->json(["message" => "Staff Management List", 'list' => "Not Exist", "code" => 400]);
        } else {
            return response()->json(["message" => "Staff Management List", 'list' => "Exist", "code" => 200]);
        }
    }

    public function deleteStaff(Request $request)
    {
        try {
            // Start the transaction
            DB::beginTransaction();
        
            // Fetch the user ID based on staff ID
            $getUserId = User::select('id')->where('staff_id', $request->staff_id)->first();
        
            // Check if user exists before proceeding with deletion
            if (!$getUserId) {
                throw new Exception('User not found');
            }
        
            // Perform deletions
            $screenAccessRolesDeleted = ScreenAccessRoles::where('staff_id', $getUserId->id)->delete();
            $userDeleted = User::where('staff_id', $request->staff_id)->delete();
            $staffManagementDeleted = StaffManagement::where('staff_id', $request->staff_id)->delete();
        
            // Check if all deletions were successful
            if ($userDeleted && $staffManagementDeleted) {
                // Commit the transaction
                DB::commit();
                return response()->json(["message" => "deleted", "code" => 200]);
            } else {
                // Rollback the transaction if any deletion failed
                DB::rollBack();
                return response()->json(['error' => 'Item could not be deleted'], 400);
            }
        } catch (Exception $e) {
            // Rollback the transaction in case of an exception
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 404);
        }

   
   }


    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////
  


}
