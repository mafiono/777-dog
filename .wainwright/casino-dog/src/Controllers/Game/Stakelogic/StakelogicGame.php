<?php
namespace Wainwright\CasinoDog\Controllers\Game\Stakelogic;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Wainwright\CasinoDog\Facades\ProxyHelperFacade;
use Wainwright\CasinoDog\Controllers\Game\GameKernelTrait;
use Illuminate\Http\Client\ConnectionException;
use Wainwright\CasinoDog\Controllers\Game\GameKernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Wainwright\CasinoDog\Controllers\Game\OperatorsController;

class StakelogicGame extends StakelogicMain
{
    use GameKernelTrait;

    public function game_event($request)
    {
        $real_gameserver_url = $request->origin;
        $real_response = $this->curl_request($real_gameserver_url, $request);
        $internal_token = $request->internal_token;
        $select_session = $this->get_internal_session($internal_token)['data'];

        $data_origin = json_decode($real_response->getContent(), true);
        
        if(isset($data_origin['game']['mode'])) {
            $data_origin['game']['mode'] = "REAL";
            
        }
        
        if(isset($data_origin['game']['walletAfterLastBet'])) {
            $data_origin['game']['walletAfterLastBet']['fm'] = ($this->get_balance($internal_token) / 100);
            if($select_session['currency'] === "USD") {
                $data_origin['game']['walletAfterLastBet']['currencyCode'] = "USD";
                $data_origin['game']['walletAfterLastBet']['currencySymbol'] = "$";
            }
        }


        if(isset($data_origin['game']['currentWallet'])) {
            if($select_session['currency'] === "USD") {
                $data_origin['game']['currentWallet']['currencyCode'] = "USD";
                $data_origin['game']['currentWallet']['currencySymbol'] = "$";
            }

            $origin_balance = Cache::get($internal_token.'::stakelogicOriginBalance');
            if(!$origin_balance) {
                Cache::set($internal_token.'::stakelogicOriginBalance', $data_origin['game']['currentWallet']['fm']);
                $origin_balance = Cache::get($internal_token.'::stakelogicOriginBalance');
            }
            $new_balance = $data_origin['game']['currentWallet']['fm'];

            if($origin_balance !== $new_balance) {
                if($origin_balance > $new_balance) {
                    $winAmount = 0;
                    $betAmount = (($origin_balance - $new_balance) * 100);
                } else {
                    $betAmount = 0;
                    $winAmount = (($origin_balance - $new_balance) * 100);
                }
                Cache::set($internal_token.'::stakelogicOriginBalance', $data_origin['game']['currentWallet']['fm']);
                $process_and_get_balance = $this->process_game($internal_token, $betAmount, $winAmount, $data_origin);
                $data_origin['game']['currentWallet']['fm'] = floatval(($process_and_get_balance / 100));
                $data_origin['game']['currentWallet']['rm'] = floatval(($process_and_get_balance / 100));
            } else {
                Cache::set($internal_token.'::stakelogicOriginBalance', $data_origin['game']['currentWallet']['fm']);
                $balance_call = ($this->get_balance($internal_token) / 100);
                $data_origin['game']['currentWallet']['fm'] = $balance_call;
                $data_origin['game']['currentWallet']['rm'] = $balance_call;
            }
        }


        return $data_origin;
    }

    public function curl_request($url, $request)
    {
        $response = ProxyHelperFacade::CreateProxy($request)->toUrl($url);
        return $response;
    }

    public function example()
    {
        $internal_token = $request->internal_token;
        $select_session = $this->get_internal_session($internal_token)['data'];
        $url = 'https://'.$select_session['token_original'].'.mascot.games/mascotGaming/spin.php';

        $request_arrayable = $request->toArray(); //we are cloning the request and changing to the minimum bet amount, this because demo balance on mascot is only 100 credits after we sent we will map back to original bet

        $action = $request_arrayable['action'];
        if($action === 'init') { //store values that can come in handy from init, as init is sending stuff you won't get later
            $init_response = $this->curl_request($request, $url);
            $data_origin = json_decode($init_response->getContent(), true);
            Cache::set($internal_token.'::mascot_gameconfig::bet_coins', $data_origin['betCoins']);
            Cache::set($internal_token.'::mascot_gameconfig::bet_sizes', $data_origin['bets']);
            Cache::set($internal_token.'::mascot_gameconfig::min_bet', $data_origin['bet']);
            Cache::set($internal_token.':mascotHiddenBalance:'.$select_session['token_original'], (int) $data_origin['balance']);
            $data_origin['balance'] = $this->get_balance($internal_token);
            return $data_origin;
        }

        $min_bet = (int) Cache::get($internal_token.'::mascot_gameconfig::min_bet');

        if(isset($request_arrayable['bet'])) {
            $original_bet = $request_arrayable['bet'];
            $request_arrayable['bet'] = $min_bet;
            $request = (clone $request)->replace($request_arrayable); // build a new request with existing original headers from player, we are only replacing body content
        }
        $response = $this->curl_request($request, $url);
        $data_origin = json_decode($response->getContent(), true);
       
        /* 
        // Example of respinning game results by creating new session:
        if($data_origin['nextAction'] === 'freespin' && $action === 'spin') {
            $this->session_transfer($internal_token);
            $select_session = $this->get_internal_session($internal_token)['data'];
            $url = 'https://'.$select_session['token_original'].'.mascot.games/mascotGaming/spin.php';
            $request_arrayable['action'] = 'spin';
            $request = (clone $request)->replace($request_arrayable); // build a new request with existing original headers from player, we are only replacing body content
            $response = $this->curl_request($request, $url);
            $data_origin = json_decode($response->getContent(), true);
            $data_origin['buyin'] = NULL;
        }
        */


        if($action === 'spin' || $action === 'drop' || $action === 'freespin' || $action === 'respin') { // map back to the real bet amounts
            if(isset($data_origin['bet'])) {
                if($original_bet !== $data_origin['bet']) {
                    $data_origin['bet'] = $original_bet;
                    if(isset($data_origin['totalWin'])) {
                        $data_origin['totalWin'] = ($data_origin['totalWin'] / $min_bet) * $original_bet;
                    }
                    if(isset($data_origin['win'])) {
                        $data_origin['win'] = ($data_origin['win'] / $min_bet) * $original_bet;
                    }
                    if(isset($data_origin['freespins'])) {
                        if(isset($data_origin['freespins']['win'])) {
                            $data_origin['freespins']['win'] = ($data_origin['freespins']['win']  / $min_bet) * $original_bet;
                        }
                    }
                    if(isset($data_origin['buyin'])) {
                        if(isset($data_origin['buyin']['bet'])) {
                            $buyin_amount = ($data_origin['buyin']['bet'] / $min_bet) * $original_bet;
                            Cache::set($internal_token.':mascotHiddenBuyinAmount', (int) $buyin_amount);
                            $data_origin['buyin']['bet'] = $buyin_amount;
                        }
                    }
                    if(isset($data_origin['dropWin'])) {
                            $data_origin['dropWin'] = ($data_origin['dropWin'] / $min_bet) * $original_bet;
                    }
                };
            }
        }
        
        if($action === 'buyin') { // buyin feature, based on cache that set before as mascot is using variable buyin feature cost amount
            $buyin_amount = (int) Cache::get($internal_token.':mascotHiddenBuyinAmount');
            $data_origin['bet'] = $buyin_amount;
            $process_and_get_balance = $this->process_game($internal_token, ($buyin_amount), 0, $data_origin);
            $data_origin['balance'] = (int) $process_and_get_balance;
            return $data_origin;
        }

        // calculate balance differences from real session, multiplied by the bet value (as balance differences will be on min. bet settings)
        // we store the previous balance in cache, if it is missing we will set it to the current balance
        $bridge_balance = (int) Cache::get($internal_token.':mascotHiddenBalance:'.$select_session['token_original']);
        if(!$bridge_balance) {
            $bridge_balance = Cache::set($internal_token.':mascotHiddenBalance:'.$select_session['token_original'], (int) $data_origin['balance']);
        }
        $current_balance = (int) $data_origin['balance'];
        if($bridge_balance !== $current_balance) {
            if($bridge_balance > $current_balance) {
                $winAmount = 0;
                $betAmount = (($bridge_balance - $current_balance)  / $min_bet) * $original_bet;
            } else {
                $betAmount = 0;
                $winAmount = (($current_balance - $bridge_balance) / $min_bet) * $original_bet;
            }
        Cache::set($internal_token.':mascotHiddenBalance:'.$select_session['token_original'],(int) $current_balance);
        $process_and_get_balance = $this->process_game($internal_token, ($betAmount ?? 0), ($winAmount ?? 0), $data_origin);
        $data_origin['balance'] = (int) $process_and_get_balance;
        } else {
            Cache::set($internal_token.':mascotHiddenBalance:'.$select_session['token_original'], (int) $current_balance);
            $get_balance = $this->get_balance($internal_token);
            $data_origin['balance'] = (int) $get_balance;
        }

        $hidden_balance = (int) Cache::get($internal_token.':mascotHiddenBalance:'.$select_session['token_original']);
        if($hidden_balance < 2500) { // let's create new _real_ session in background when real session's balance is running low (2500 is if below 25$)
            if(isset($data_origin['nextAction'])) {
                if($data_origin['nextAction'] === "spin") {
                    $this->session_transfer($internal_token);
                }
            }
        }

        if(env('APP_DEBUG') === true)
        { // add extra data in debug/testing
        $data_origin['dog'] = [];
        $data_origin['dog']['real_balance'] = $hidden_balance;
        $data_origin['dog']['real_session'] = $select_session['token_original'];
        $data_origin['dog']['internal_token'] = $internal_token;
        }
        return $data_origin;
    }



}
