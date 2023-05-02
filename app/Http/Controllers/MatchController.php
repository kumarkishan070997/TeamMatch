<?php

namespace App\Http\Controllers;

use DB;
use App\Models\Team;
use App\Models\Finale;
use App\Models\SemiFinale;
use App\Models\QuaterFinale;
use Illuminate\Http\Request;

class MatchController extends Controller
{
    public function fetchMatchDetail(Request $request){
        try{
            DB::table('teams')->truncate();
            DB::table('quater_finale')->truncate();
            DB::table('semi_finale')->truncate();
            DB::table('finale')->truncate();
            $data = $request->all();
            // No. of teams in  group 1
            $grp1_team_no = $data['grp_1_team_number'];
            $grp2_team_no = $grp1_team_no+1;
    
            $loop_count = 1;
    
            $grp1_name_prefix = "team_a_";
            $grp2_name_prefix = "team_b_";
    
            // making team names for 2 groups and storing the team details in teams table
            $grp1_array = [];
            $grp2_array = [];
            while($loop_count <= $grp1_team_no){
                $grp1_array[] = [
                    'name' => $grp1_name_prefix.$loop_count,
                    'group_id' => 1
                ];
                $grp2_array[] = [
                    'name' => $grp2_name_prefix.$loop_count,
                    'group_id' => 2
                ];
                $loop_count++;
            }
            
            // adding one more team to group 2
            $grp2_array[] = [
                'name' => $grp2_name_prefix.$loop_count,
                'group_id' => 2
            ];

            // storing the team detail inside parent table that is teams
            DB::table('teams')->insert(array_merge($grp1_array,$grp2_array));


            // following function will do the league match between two teams and find the quarter finalist

            $this->getQuarterFinalist($data,$grp1_array,$grp2_array);

            // after getting 4 quater finalist we have to find 3 semi finalist, following function will do the same

            $this->getSemiFinalist($data,$grp1_array,$grp2_array);

            // after getting 3 semi finalist we have to find 2  finalist, following function will do the same

            $this->getFinalist($data,$grp1_array,$grp2_array);

            // here finally we got 2 finalist which we have stored on finale table ... following is the code to make desired response

            // queries to get the desired output
            $response = $this->getAllDatafromTable();

            // making response here

            $result = [];

            // $result['all_teams'] = $response['all_teams'];
           foreach($response['all_teams'] as $key => $value){
                $result['all_teams']["group_".$key] = $value;
           }
           foreach($response['quarter_finalists'] as $key => $value){
                $result['quarter_finalists'][] = [
                    'id' => $value['id'],
                    'team_id' => $value['team_id'],
                    'group_id' => $value['group_id'],
                    'points' => $value['points'],
                    'team_name' => $value['team']['name']
                ];
           }

           foreach($response['semi_finalists'] as $key => $value){
                $result['semi_finalists'][] = [
                    'id' => $value['id'],
                    'team_id' => $value['team_id'],
                    'group_id' => $value['group_id'],
                    'points' => $value['points'],
                    'team_name' => $value['team']['name']
                ];
            }

            foreach($response['finalists'] as $key => $value){
                $result['finalists'][] = [
                    'id' => $value['id'],
                    'team_id' => $value['team_id'],
                    'group_id' => $value['group_id'],
                    'points' => $value['points'],
                    'team_name' => $value['team']['name']
                ];
           }
           // finding the winner here
           $winner = $this->getWinner($response['finalists']);
           $winner_data = DB::table('teams')->where('id',$winner)->first();
           $result['winner'] = $winner_data->name ?? '';
           return response()->json(['response' => ['code' => '200', 'message' => 'Match data fetched successfully.', 'data' => $result]]);

        }catch(\Exception $e){
            Log::error(
                'Failed to fetch list',
                ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]
            );
            return response()->json(['response' => ['code' => '500', 'message' => 'Unexpected error has been occured.']]);
        }

    }
    public function getQuarterFinalist($data,$grp1_array,$grp2_array){
        try{
            // fetch the last team from grp 2 and by default mark it as quarter finalist

            $last_team = DB::table('teams')->where('group_id',2)->orderBy('id','desc')->first();
            if($last_team && $last_team != null){
                DB::table('quater_finale')->insert([
                    'team_id' => $last_team->id,
                    'group_id' => $last_team->group_id,
                    'points' => 0
                ]);
            }

            // code to match between 2 groups
            $temp_arr = [];
            foreach($grp1_array as $key => $value){
                for($i=0;$i<count($grp2_array)-1;$i++){
                    // writting the following code two times because each team will play with the opponent team for twice
                    $winner = rand(0, 1) ? $value['name'] : $grp2_array[$i]['name'];
                    if(array_key_exists($winner,$temp_arr)){
                        $temp_arr[$winner] = $temp_arr[$winner] + 2;
                    }else{
                        $temp_arr[$winner] = 2;
                    }

                    $winner = rand(0, 1) ? $value['name'] : $grp2_array[$i]['name'];
                    if(array_key_exists($winner,$temp_arr)){
                        $temp_arr[$winner] = $temp_arr[$winner] + 2;
                    }else{
                        $temp_arr[$winner] = 2;
                    }
                }
            }
            arsort($temp_arr);
            $array_keys = array_keys($temp_arr);
            // i have already 1 member is in quater finale now i need only three more teams in quater finale.
            $query_1 = DB::table('teams')->whereIn('name',[$array_keys[0],$array_keys[1],$array_keys[2]])->get();
            if($query_1 && $query_1 != null){
                foreach($query_1 as $value){
                    DB::table('quater_finale')->insert([
                        [
                            'team_id'=>$value->id,
                            'group_id'=>$value->group_id,
                            'points'=> $temp_arr[$value->name]
                        ]
                    ]);
                }
            }
            return true;

        }catch(\Exception $e){
            Log::error(
                'Failed to fetch list',
                ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]
            );
            return response()->json(['response' => ['code' => '500', 'message' => 'Unexpected error has been occured.']]);
        }
    }
    public function getSemiFinalist($data,$grp1_array,$grp2_array){
        try{
            // fetch the last team from grp 2 and by default mark it as quarter finalist

            $quater_finale_teams = DB::table('quater_finale')->get()->toArray();

            // code to match between 2 groups
            $temp_arr = [];
            for($i=0;$i<count($quater_finale_teams);$i++){
                for($j=$i+1;$j<count($quater_finale_teams);$j++){
                    $winner = rand(0, 1) ? $quater_finale_teams[$i]->id : $quater_finale_teams[$j]->id;
                    if(array_key_exists($winner,$temp_arr)){
                        $temp_arr[$winner] = $temp_arr[$winner] + 2;
                    }else{
                        $temp_arr[$winner] = 2;
                    }

                    $winner = rand(0, 1) ? $quater_finale_teams[$i]->id : $quater_finale_teams[$j]->id;
                    if(array_key_exists($winner,$temp_arr)){
                        $temp_arr[$winner] = $temp_arr[$winner] + 2;
                    }else{
                        $temp_arr[$winner] = 2;
                    }
                }
            }
            arsort($temp_arr);
            $array_keys = array_keys($temp_arr);
            // i have already 1 member is in quater finale now i need only three more teams in quater finale.
            $query_1 = DB::table('teams')->whereIn('id',[$array_keys[0],$array_keys[1],$array_keys[2]])->get();
            if($query_1 && $query_1 != null){
                foreach($query_1 as $value){
                    DB::table('semi_finale')->insert([
                        [
                            'team_id'=>$value->id,
                            'group_id'=>$value->group_id,
                            'points'=> $temp_arr[$value->id]
                        ]
                    ]);
                }
            }
            return true;

        }catch(\Exception $e){
            Log::error(
                'Failed to fetch list',
                ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]
            );
            return response()->json(['response' => ['code' => '500', 'message' => 'Unexpected error has been occured.']]);
        }
    }
    public function getFinalist($data,$grp1_array,$grp2_array){
        try{
            // fetch the last team from grp 2 and by default mark it as quarter finalist

            $quater_finale_teams = DB::table('semi_finale')->get()->toArray();

            // code to match between 2 groups
            $temp_arr = [];
            for($i=0;$i<count($quater_finale_teams);$i++){
                for($j=$i+1;$j<count($quater_finale_teams);$j++){
                    $winner = rand(0, 1) ? $quater_finale_teams[$i]->id : $quater_finale_teams[$j]->id;
                    if(array_key_exists($winner,$temp_arr)){
                        $temp_arr[$winner] = $temp_arr[$winner] + 2;
                    }else{
                        $temp_arr[$winner] = 2;
                    }

                    $winner = rand(0, 1) ? $quater_finale_teams[$i]->id : $quater_finale_teams[$j]->id;
                    if(array_key_exists($winner,$temp_arr)){
                        $temp_arr[$winner] = $temp_arr[$winner] + 2;
                    }else{
                        $temp_arr[$winner] = 2;
                    }
                }
            }
            arsort($temp_arr);
            $array_keys = array_keys($temp_arr);
            // i have already 1 member is in quater finale now i need only three more teams in quater finale.
            $query_1 = DB::table('teams')->whereIn('id',[$array_keys[0],$array_keys[1]])->get();
            if($query_1 && $query_1 != null){
                foreach($query_1 as $value){
                    DB::table('finale')->insert([
                        [
                            'team_id'=>$value->id,
                            'group_id'=>$value->group_id,
                            'points'=> $temp_arr[$value->id]
                        ]
                    ]);
                }
            }
            return true;

        }catch(\Exception $e){
            Log::error(
                'Failed to fetch list',
                ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]
            );
            return response()->json(['response' => ['code' => '500', 'message' => 'Unexpected error has been occured.']]);
        }
    }
    public function getAllDatafromTable(){
        try{
            // fetch all teams
            $all_teams = Team::get()->groupBy('group_id');
            
            // fetch all quater finalist
            $quarter_finalists = QuaterFinale::with('team')->get();
            $semi_finalists = SemiFinale::with('team')->get();
            $finalists = Finale::with('team')->get();
            return $list = [
                'all_teams' => $all_teams ?? [],
                'quarter_finalists' => $quarter_finalists ?? [],
                'semi_finalists' => $semi_finalists ?? [],
                'finalists' => $finalists ?? []
            ];
        }catch(\Exception $e){
            Log::error(
                'Failed to fetch list',
                ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]
            );
            return response()->json(['response' => ['code' => '500', 'message' => 'Unexpected error has been occured.']]);
        }
    }
    public function getWinner($finalists){
        try{
            $temp_arr = [];
            $winner = rand(0, 1) ? $finalists[0]['team_id'] : $finalists[1]['team_id'];
            if(array_key_exists($winner,$temp_arr)){
                $temp_arr[$winner] = $temp_arr[$winner] + 2;
            }else{
                $temp_arr[$winner] = 2;
            }

            $winner = rand(0, 1) ? $finalists[0]['team_id'] : $finalists[1]['team_id'];
            if(array_key_exists($winner,$temp_arr)){
                $temp_arr[$winner] = $temp_arr[$winner] + 2;
            }else{
                $temp_arr[$winner] = 2;
            }
            arsort($temp_arr);
            $temp_arr = array_keys($temp_arr);
            return $temp_arr[0];

        }catch(\Exception $e){
            Log::error(
                'Failed to fetch list',
                ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]
            );
            return response()->json(['response' => ['code' => '500', 'message' => 'Unexpected error has been occured.']]);
        }
    }
}
