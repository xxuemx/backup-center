<?php
// Standalone regression suite: run in an isolated container, never on the live DB.
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','app.key'=>'base64:'.base64_encode(random_bytes(32))]);
DB::purge('sqlite');
if (DB::connection()->getDatabaseName() !== ':memory:') throw new RuntimeException('not isolated');
Artisan::call('migrate', ['--force'=>true]);
$notifications=new class extends App\Services\NotificationDispatcher {
 public array $seen=[];
 public function notifyBackupCompleted(App\Models\BackupLog $log):void { $this->seen[]=$log->status; }
};
$app->instance(App\Services\NotificationDispatcher::class,$notifications);
function check($condition,$message){if(!$condition)throw new RuntimeException($message);echo "PASS $message\n";}
$server=App\Models\Server::create(['name'=>'test','connect_type'=>'agent']);
$site=App\Models\Website::create(['name'=>'test','server_id'=>$server->id,'status'=>'enabled']);
$a=App\Models\Storage::create(['name'=>'a','type'=>'sftp','status'=>'enabled','config'=>[]]);
$b=App\Models\Storage::create(['name'=>'b','type'=>'sftp','status'=>'enabled','config'=>[]]);
$site->storages()->sync([$a->id,$b->id]);
$controller=new App\Http\Controllers\Api\AgentController;
$request=Illuminate\Http\Request::create('/','POST',['website_id'=>$site->id,'filename'=>'test.tar.gz','backup_at'=>'2026-10-15T19:00:00+08:00','status'=>'success']);
$request->attributes->set('server',$server);
$controller->report($request);
$log=App\Models\BackupLog::firstOrFail();
check($log->status==='running','packing alone is not success');
check($log->backup_at->toIso8601String()==='2026-10-15T11:00:00+00:00','offset normalized to UTC');
check(count($notifications->seen)===0,'no premature notification');
$upload=App\Models\BackupUpload::create(['backup_log_id'=>$log->id,'storage_id'=>$a->id,'status'=>'uploaded']);
$outcome=new App\Services\BackupOutcome;
$outcome->refresh($log);
check($log->fresh()->status==='running','one of two targets is not success');
$second=App\Models\BackupUpload::create(['backup_log_id'=>$log->id,'storage_id'=>$b->id,'status'=>'failed']);
$outcome->refresh($log);
check($log->fresh()->status==='failed','partial failure is failure');
check($site->fresh()->last_backup_status==='failed','website summary matches failure');
check($notifications->seen===['failed'],'failure notified once');
$outcome->refresh($log);
check($notifications->seen===['failed'],'duplicate report does not duplicate notification');
$controller->report($request);
check($log->fresh()->status==='failed','repeated source report does not erase failure');
$second->update(['status'=>'uploaded']);
$outcome->refresh($log);
check($log->fresh()->status==='success','retry with all targets successful completes');
check($notifications->seen===['failed','success'],'recovery notified only after uploads');
$site->storages()->detach();
$request->merge(['filename'=>'no-target.tar.gz']);
$controller->report($request);
check(App\Models\BackupLog::where('filename','no-target.tar.gz')->first()->status==='failed','zero targets fails closed');
echo "All regression checks passed.\n";
