<?php namespace EENPC;

$military_list = array('m_tr','m_j','m_tu','m_ta');

function play_indy_strat($server)
{
    global $cnum;
    out("Playing ".INDY." Turns for #$cnum");
    $main = get_main();     //get the basic stats
    //out_data($main);			//output the main data
    $c = get_advisor();     //c as in country! (get the advisor)
    out("Indy: {$c->pt_indy}%; Bus: {$c->pt_bus}%; Res: {$c->pt_res}%");
    //out_data($c) && exit;				//ouput the advisor data
    if ($c->govt == 'M') {
        $rand = rand(0, 100);
        switch ($rand) {
            case $rand < 5:
                change_govt($c, 'I');
                break;
            case $rand < 5:
                change_govt($c, 'D');
                break;
            default:
                change_govt($c, 'C');
                break;
        }
    }
    out($c->turns.' turns left');
    $pm_info = get_pm_info();   //get the PM info
    //out_data($pm_info);		//output the PM info
    $market_info = get_market_info();   //get the Public Market info
    //out_data($market_info);		//output the PM info

    $owned_on_market_info = get_owned_on_market_info();     //find out what we have on the market
    //out_data($owned_on_market_info);	//output the Owned on Public Market info

    while ($c->turns > 0) {
        //$result = buy_public($c,array('m_bu'=>100),array('m_bu'=>400));
        $result = play_indy_turn($c);
        if ($result === false) {  //UNEXPECTED RETURN VALUE
            $c = get_advisor();     //UPDATE EVERYTHING
            continue;
        }
        update_c($c, $result);
        if (!$c->turns%5) {                   //Grab new copy every 5 turns
            $main = get_main();         //Grab a fresh copy of the main stats //we probably don't need to do this *EVERY* turn
            $c->money = $main->money;       //might as well use the newest numbers?
            $c->food = $main->food;             //might as well use the newest numbers?
            $c->networth = $main->networth; //might as well use the newest numbers?
            $c->oil = $main->oil;           //might as well use the newest numbers?
            $c->pop = $main->pop;           //might as well use the newest numbers?
            $c->turns = $main->turns;       //This is the only one we really *HAVE* to check for
        }

        $hold = money_management($c);
        if ($hold) {
            break; //HOLD TURNS HAS BEEN DECLARED; HOLD!!
        }

        $hold = food_management($c);
        if ($hold) {
            break; //HOLD TURNS HAS BEEN DECLARED; HOLD!!
        }
        global $cpref;
        $tol = $cpref->price_tolerance; //should be between 0.5 and 1.5
        if (turns_of_food($c) > 40 && $c->money > $c->networth *2 && $c->money > 3500*500) { // 40 turns of food, and more than 2x nw in cash on hand
            //out("Try to buy tech?");
            $spend = $c->money * 0.10;
            if ($c->pt_indy < 140) {
                buy_tech($c, 't_indy', $spend*1/2, 3500*$tol);
            }
            if ($c->pt_bus < 140) {
                buy_tech($c, 't_bus', $spend*1/4, 3500*$tol);
            }
            if ($c->pt_res < 140) {
                buy_tech($c, 't_res', $spend*1/4, 3500*$tol);
            }

            $c = get_advisor();     //UPDATE EVERYTHING
            if (turns_of_food($c) > 40 && $c->money > $c->networth *2 && $c->money > 3500*500) { // 40 turns of food, and more than 2x nw in cash on hand
                $spend = $c->money * 0.10;
                if ($c->pt_indy < 150) {
                    buy_tech($c, 't_indy', $spend*1/2, 3500*$tol);
                }
                if ($c->pt_bus < 160) {
                    buy_tech($c, 't_bus', $spend*1/4, 3500*$tol);
                }
                if ($c->pt_res < 160) {
                    buy_tech($c, 't_res', $spend*1/4, 3500*$tol);
                }
            }
        }
    }
    out("Indy: {$c->pt_indy}%; Bus: {$c->pt_bus}%; Res: {$c->pt_res}%");
    out("Done Playing ".INDY." Turns for #$cnum!");     //Text for screen
}

function play_indy_turn(&$c)
{
 //c as in country!
    $target_bpt = 65;
    global $turnsleep;
    usleep($turnsleep);
    //out($main->turns . ' turns left');
    if ($c->protection == 0 && total_cansell_military($c) > 7500 && sellmilitarytime($c)) {
        return sell_max_military($c);
    } elseif ($c->empty > $c->bpt && $c->money > $c->bpt*$c->build_cost + ($c->income > 0 ? 0 : $c->income*-60)) {  //build a full BPT if we can afford it
        return build_indy($c);
    } elseif ($c->turns >= 4 && $c->empty >= 4 && $c->bpt < $target_bpt && $c->money > 4*$c->build_cost && ($c->foodnet > 0 || $c->food > $c->foodnet*-5)) { //otherwise... build 4CS if we can afford it and are below our target BPT (80)
        return build_cs(4); //build 4 CS
    } elseif ($c->empty < $c->land/2) {  //otherwise... explore if we can
        return explore($c);
    } elseif ($c->empty && $c->bpt < $target_bpt && $c->money > $c->build_cost) { //otherwise... build one CS if we can afford it and are below our target BPT (80)
        return build_cs(); //build 1 CS
    } else { //otherwise...  cash
        return cash($c);
    }
}

function build_indy(&$c)
{
    //build farms
    return build(array('indy' => $c->bpt));
}


function sellmilitarytime(&$c)
{
    global $military_list;
    $sum = $om = 0;
    foreach ($military_list as $mil) {
        $sum += $c->$mil;
        $om += onmarket($mil);
    }
    if ($om < $sum/6) {
        return true;
    }

    return false;
}
