<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Helpers\Curl;
use App\Models\DeviceGroup;
class GroupService
{
    use Curl;

    public function allGroup($request)
    {
        $id = session('tc_user_id');
        $para_data = "";
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        if ($id != '') {
            $para_data = 'id=' . $id;
        }
        $group_data = [];
        $requestData = $request->all();
        $offset = isset($requestData['offset']) ? $requestData['offset']:10;
        $limit = isset($requestData['limit']) ? $requestData['limit']:10;
        $offset2 = (int)$offset+1;

        $user = $request->user();
        // $getIds = '';
        $groupIds = [];
        $nextGroup = [];
        // if(!empty($user)){
        //     if($user->user_role==0){
        //         $all_group = DeviceGroup::orderBy('id','desc')->where(['user_id'=>$user->id]);
        //     }else{
        //         $all_group = DeviceGroup::orderBy('id','desc');
        //     }
        //     $nextGroups = $all_group->offset($offset2*$limit)->take($limit)->get();
        //     $all_group = $all_group->offset($offset*$limit)->take($limit)->orderBy('id','desc')->get();
        //     $nextGroup = $nextGroups;
        //     $groupIds = $all_group->pluck('groupId');
        //     $groupIds = $groupIds->toArray();
        // }
        // dd($nextGroup,$groupIds);
        $index = $offset;
        $group =[];
        // if(!empty($groupIds)){
            // foreach($groupIds as $key=>$value){
                // if(!empty($value)){
                    // $getIds.= $value;
                    $stoploop = 0;
                    $userid = '';
                    if($user->user_role==0){
                        $userid = "?userId=".$user->traccar_user_id;
                    }
                    // dd($userid);
                    $data = static::curl('/api/groups/'.$userid, 'GET', $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));
                    if($data->responseCode == 200 ){
                        $group = json_decode($data->response,true);
                        $group = array_reverse($group);

                        if(!empty($group)){
                            foreach($group as $key=>$value){
                                if($stoploop > $limit-1){
                                    break;
                                }
                                if(isset($group[$index])){
                                    array_push($group_data,$group[$index]);
                                    array_push($nextGroup,$group[$index]);
                                }
                                $index++;
                                $stoploop++;
                            }
                        }

                        // $group_data[$key]['attributes']['userId'] = (isset($group_data[$key]['attributes']['userId'])) ? $group_data[$key]['attributes']['userId']: $request->user()->id;
                    }
                // }
            // }
        // }
        // $group = array_push($nextGroup,['1']);
        $groupData['groups'] = $group_data;
        array_push($nextGroup,['1']);
        $groupData['nextGroup'] = $nextGroup;
        return $groupData;
    }


    public function addGroup($request)
    {
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $data = $request->all();
        $type = 'POST';
        $id = '';
        if(isset($request->id) && $request->id !==0){
            $type = 'PUT';
            $id = '/'.$request->id;
        }
        $data = json_encode($data);
        // dd($data );
        $response = static::curl('/api/groups'.$id, $type, $sessionId, $data, array('Content-Type: application/json', 'Accept: application/json'));
        return $response;
    }

    public function allnotification()
    {
        $sessionId = session('cookie');
        $type = 'GET';
        $response = static::curl('/api/notifications/types', $type, $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));
        $result = json_decode($response->response,true);
        return $result;
    }
    public function deleteGroup($request)
    {
        $id = $request->groupId;
        $sessionId = $request->user()->traccarSession ?? session('cookie');
        $response = static::curl('/api/groups/' . $id, 'DELETE', $sessionId, '', array('Content-Type: application/json', 'Accept: application/json'));
        return $response;
    }
}
?>
