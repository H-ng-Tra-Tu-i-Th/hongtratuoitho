<?php
/** Server-owned matching state adapted from the supplied Ky Uc 10 game. */
const HYMG_MATCHING_PAIRS = 6;
const HYMG_MATCHING_HIDE_MS = 950;

function hymg_matching_now(): int { return (int)floor(microtime(true)*1000); }

/** Limit request bursts per browser session, without grouping everyone on shop Wi-Fi. */
function hymg_matching_throttle(): void {
    $now=time();$rate=$_SESSION['hymg_flip_rate']??null;
    if(!is_array($rate)||!isset($rate['until'],$rate['count'])||(int)$rate['until']<=$now)$rate=['until'=>$now+60,'count'=>0];
    $rate['count']=(int)$rate['count']+1;$_SESSION['hymg_flip_rate']=$rate;
    if($rate['count']>240)hymg_fail('rate_limit',429);
}

function hymg_matching_new(): array {
    $deck=array_merge(range(0,HYMG_MATCHING_PAIRS-1),range(0,HYMG_MATCHING_PAIRS-1));
    for($i=count($deck)-1;$i>0;$i--){$j=random_int(0,$i);[$deck[$i],$deck[$j]]=[$deck[$j],$deck[$i]];}
    return ['id'=>bin2hex(random_bytes(16)),'deck'=>$deck,'open'=>[],'matched'=>[],'moves'=>0,'seq'=>0,
        'created_at'=>hymg_matching_now(),'started_at'=>null,'completed_at'=>null,'hide_at'=>0];
}

/** Return only open/matched faces; the unrevealed deck never enters an API response. */
function hymg_matching_view(array $board): array {
    $now=hymg_matching_now();
    $open=$board['hide_at']&&$board['hide_at']<=$now?[]:$board['open'];
    $faces=[];
    foreach(array_unique(array_merge($open,$board['matched'])) as $position)$faces[$position]=$board['deck'][$position];
    $end=$board['completed_at']??$now;
    return ['id'=>$board['id'],'seq'=>$board['seq'],'open'=>array_values($open),'matched'=>array_values($board['matched']),
        'faces'=>(object)$faces,'moves'=>$board['moves'],'pairs'=>intdiv(count($board['matched']),2),
        'total_pairs'=>HYMG_MATCHING_PAIRS,'started'=>$board['started_at']!==null,'complete'=>$board['completed_at']!==null,
        'elapsed_seconds'=>$board['started_at']===null?0:max(0,intdiv($end-$board['started_at'],1000)),
        'wait_ms'=>max(0,$board['hide_at']-$now)];
}

/** A stale sequence syncs the browser without turning an unintended next card. */
function hymg_matching_flip(array &$board,string $boardId,int $sequence,int $position): string {
    if(!hash_equals($board['id'],$boardId))hymg_fail('board_changed',409);
    if($position<0||$position>=HYMG_MATCHING_PAIRS*2||$sequence<0)hymg_fail('invalid',409);
    $now=hymg_matching_now();
    if($board['hide_at']&&$board['hide_at']<=$now){$board['open']=[];$board['hide_at']=0;}
    if($sequence!==$board['seq'])return 'sync';
    if($board['completed_at']!==null)return 'complete';
    if($board['hide_at']>$now)return 'wait';
    if(in_array($position,$board['matched'],true)||in_array($position,$board['open'],true))return 'ignored';
    $board['started_at']??=$now;
    $board['open'][]=$position;
    $board['seq']++;
    if(count($board['open'])===1)return 'first';
    $board['moves']++;
    [$first,$second]=$board['open'];
    if($board['deck'][$first]===$board['deck'][$second]){
        $board['matched']=array_merge($board['matched'],$board['open']);$board['open']=[];
        if(count($board['matched'])===HYMG_MATCHING_PAIRS*2)$board['completed_at']=$now;
        return $board['completed_at']!==null?'complete':'match';
    }
    $board['hide_at']=$now+HYMG_MATCHING_HIDE_MS;
    return 'mismatch';
}
