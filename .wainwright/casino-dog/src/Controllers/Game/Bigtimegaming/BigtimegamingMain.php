<?php
namespace Wainwright\CasinoDog\Controllers\Game\Bigtimegaming;

use Wainwright\CasinoDog\Controllers\Game\GameKernel;
use Wainwright\CasinoDog\Controllers\Game\GameKernelTrait;
use Illuminate\Http\Request;

class BigtimegamingMain extends GameKernel
{
    use GameKernelTrait;

    /*
    * load_game_session() is where we create/continue any game session and where we initiate the game content
    *
    * @param [type] $data
    * @return void
    */
    public function load_game_session($data) {
        $parent = new \Wainwright\CasinoDog\Controllers\Game\Relaxgaming\RelaxgamingMain;
        return $parent->load_game_session($data);
    }

    /*
    * game_launch() is where we send the finalized HTML content to the launcher blade view template
    *
    * @param [type] $game_content
    * @return void
    */
    public function game_launch($game_content) {
        $parent = new \Wainwright\CasinoDog\Controllers\Game\Relaxgaming\RelaxgamingMain;
        return $parent->game_launch($game_content);
    }

    /*
    * game_event() is where direct API requests from inside games are received
    *
    * @param Request $request
    * @return void
    */
    public function game_event(Request $request) {
        $parent = new \Wainwright\CasinoDog\Controllers\Game\Relaxgaming\RelaxgamingMain;
        return $parent->game_event($request);
    }

    /*
    * error_handle() for handling errors, meant to make similar error pages as original game but can be used for any error handling you need
    *
    * @param [type] $type
    * @param [type] $message
    * @return void
    */
    public function error_handle($type, $message = NULL) {
        if($type === 'incorrect_game_event_request') {
            $message = ['status' => 400, 'error' => $type];
            return response()->json($message, 400);
        }
        abort(400, $message);
    }


    /*
    * dynamic_asset() used to load altered javascript from internal storage, simply point the assets you need loaded through here in the modify_content() to point to /dynamic_asset/[ASSET_NAME]
    *
    * @param string $asset_name
    * @param Request $request
    * @return void
    */
    public function dynamic_asset(string $asset_name, Request $request) 
    {
    }

    /*
    * fake_iframe_url() used to display as src in iframe, this is only visual. If you have access to game aggregation you should generate a working session with game provider.
    *
    * @param string $slug
    * @param [type] $currency
    * @return void
    */
    public function fake_iframe_url(string $slug, $currency) {
        /* example *
        
            $game_id_purification = explode(':', $slug);
            if($game_id_purification[1]) {
                $game_id = $game_id_purification[1];
            }
            if($currency === 'DEMO' || $currency === 'FUN') {
                $build_url = 'https://bog.bigtimegaming.net/gs2c/openGame.do?gameSymbol='.$game_id.'&websiteUrl=https%3A%2F%2Fblueoceangaming.com&platform=WEB&jurisdiction=99&lang=en&cur='.$currency;
            }
            $build_url = 'https://bog.bigtimegaming.net/gs2c/html5Game.do?gameSymbol='.$game_id.'&websiteUrl=https%3A%2F%2Fblueoceangaming.com&platform=WEB&jurisdiction=99&lang=en&cur='.$currency;
            return $build_url;
            
        */
    }

    /*
    * default_gamelist() used to import/export a default gamelist from file storage
    *
    * @param string $action
    * @param json $data
    * @return void
    */
    public function default_gamelist($action, $data = NULL)
    {
        $storage_location = __DIR__.'/AssetStorage/default_gamelist.json';
        if (!file_exists($storage_location)) {
            file_put_contents($storage_location, '[]');
        }

        if($action === "store") {
            if($data === NULL) {
                $message = ['status' => 400, 'error' => "You need to supply data to import."];
                return $message;
            }

            if(!$this->isJSON($data)) {
                $message = ['status' => 400, 'error' => "Data does not seem to be valid JSON scheme."];
                return $message;
            }
            $store = file_put_contents($storage_location, $data);
            $message = array('status' => 200, 'message' => "Data saved at ".$storage_location);
            return $message;
        
        } elseif($action === "retrieve") {
            try {
                $storage_location = __DIR__.'/AssetStorage/default_gamelist.json';


                $retrieve = file_get_contents($storage_location);
                if($this->isJSON($retrieve)) {
                    $message = ['status' => 200, 'message' => $retrieve];
                } else {
                    $message = ['status' => 400, 'error' => "Data retrieved at '.$storage_location.' not seem to be valid JSON scheme."];

                }
                } catch(\Exception $e) {
                    $message = ['status' => 400, 'error' => $e->getMessage()];
                }
            return $message;
        } elseif($action === "get_storage_location") {
            $message = ['status' => 200, 'message' => $storage_location];
            return $message;
        }

        $message = ['status' => 400, 'error' => $action." action not valid in default_gamelist() function."];
        return $message;
    }

    /*
    * custom_entry_path() used for structuring the path the launcher is displayed on. You need to enable this in config ++ then copy the "/g" route in routes/games.php to reflect the custom entry path used below.
    *
    * @param [type] $gid
    * @return void
    */
    public function custom_entry_path($gid)
    {
        $parent = new Wainwright\CasinoDog\Controllers\Game\Relaxgaming\RelaxgamingMain;
        return $parent->custom_entry_path($gid);
    }

    /*
    * modify_game() used for replacing HTML content
    *
    * @param [type] $token_internal
    * @param [type] $game_content
    * @return void
    */
    public function modify_game($token_internal, $game_content)
    {

    }
}