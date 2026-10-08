<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Fsp\MatchEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ComparisonController extends Controller
{
    public function companies(Request $request, MatchEngine $engine): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->role === 'applicant', 403);
        $filters = $request->validate([
            'city' => ['nullable','string','max:100'], 'grade' => ['nullable','in:intern,junior,middle,senior,lead'],
            'format' => ['nullable','in:office,hybrid,remote,any'], 'score_min' => ['nullable','integer','min:0','max:100'],
            'q' => ['nullable','string','max:120'], 'sort' => ['nullable','in:score_desc,salary_desc,quality_desc,response_asc,company_asc,city_asc'],
        ]);

        $query = DB::table('jobs as j')->leftJoin('companies as c','c.company_id','=','j.company_id')->where('j.status','open')
            ->select('j.job_id','j.company_id','j.title','j.location','j.grade_required','j.salary_min','j.salary_max','j.work_format','c.name as company_name','c.city as company_city','c.industry','c.size_band','c.hiring_quality_score','c.avg_response_hours','c.acceptance_rate');
        if (Schema::hasColumn('companies','verification_status')) $query->addSelect('c.verification_status');
        if (Schema::hasColumn('companies','trust_score')) $query->addSelect('c.trust_score');
        if (!empty($filters['city'])) $query->where(fn($q)=>$q->where('j.location',$filters['city'])->orWhere('c.city',$filters['city']));
        if (!empty($filters['grade'])) $query->where('j.grade_required',$filters['grade']);
        if (!empty($filters['format'])) $query->where('j.work_format',$filters['format']);
        if (!empty($filters['q'])) $query->where(fn($q)=>$q->where('j.title','like','%'.$filters['q'].'%')->orWhere('c.name','like','%'.$filters['q'].'%'));

        $best=[];
        foreach ($query->get() as $job) {
            $match=$engine->compare((int)$user->getKey(),(int)$job->job_id); $id=(int)($job->company_id??0);
            if ($id<=0 || (isset($best[$id]) && ($best[$id]['score']??0)>=($match['score']??0))) continue;
            $best[$id]=[
                'companyId'=>$id,'company'=>$job->company_name??'Компания','companyCity'=>$job->company_city,'industry'=>$job->industry,'size'=>$job->size_band,
                'quality'=>$job->hiring_quality_score===null?null:(float)$job->hiring_quality_score,'responseHours'=>$job->avg_response_hours===null?null:(float)$job->avg_response_hours,
                'acceptanceRate'=>$job->acceptance_rate===null?null:(float)$job->acceptance_rate,'verification'=>property_exists($job,'verification_status')?$job->verification_status:'legacy',
                'trust'=>property_exists($job,'trust_score')&&$job->trust_score!==null?(float)$job->trust_score:null,'jobId'=>(int)$job->job_id,'job'=>$job->title,'location'=>$job->location,
                'grade'=>$job->grade_required,'salaryMin'=>$job->salary_min===null?null:(int)$job->salary_min,'salaryMax'=>$job->salary_max===null?null:(int)$job->salary_max,'workFormat'=>$job->work_format,
                ...$this->matchPayload($match),
            ];
        }
        $items=collect(array_values($best));
        if (isset($filters['score_min'])) $items=$items->filter(fn(array $r)=>(int)($r['score']??0)>=(int)$filters['score_min']);
        return response()->json(['items'=>$this->sortCompanies($items,$filters['sort']??'score_desc')->values()->all(),'filters'=>$filters]);
    }

    public function candidates(Request $request, MatchEngine $engine): JsonResponse
    {
        /** @var User $user */
        $user=$request->user(); abort_unless($user->role==='employer',403);
        $filters=$request->validate([
            'city'=>['nullable','string','max:100'],'category'=>['nullable','in:Backend,Frontend,Data,ML/AI,DevOps,Full-stack'],'grade'=>['nullable','in:intern,junior,middle,senior,lead'],
            'format'=>['nullable','in:office,hybrid,remote,any'],'score_min'=>['nullable','integer','min:0','max:100'],'assessment_min'=>['nullable','numeric','min:0','max:100'],
            'integrity_min'=>['nullable','numeric','min:0','max:100'],'q'=>['nullable','string','max:120'],'sort'=>['nullable','in:score_desc,assessment_desc,integrity_desc,evidence_desc'],
        ]);
        $companyId=DB::table('company_members')->where('user_id',$user->getKey())->where('active',true)->value('company_id'); abort_unless($companyId,422,'Работодатель не привязан к компании.');
        $jobs=DB::table('jobs')->where('company_id',$companyId)->where('status','open')->get(); abort_if($jobs->isEmpty(),422,'Сначала создайте открытую вакансию.');

        $query=DB::table('users')->where('role','applicant');
        if (Schema::hasColumn('users','profile_visibility')) $query->where('profile_visibility','employers');
        if (Schema::hasColumn('users','consent_processing')) $query->where('consent_processing',true);
        if (Schema::hasColumn('users','consent_publication')) $query->where('consent_publication',true);
        if (!empty($filters['city'])) $query->where('city',$filters['city']); if (!empty($filters['category'])) $query->where('primary_category',$filters['category']);
        if (!empty($filters['grade'])) $query->where('confirmed_grade',$filters['grade']); if (!empty($filters['format'])) $query->where('work_format',$filters['format']);
        if (!empty($filters['q'])) $query->where(fn($q)=>$q->where('username','like','%'.$filters['q'].'%')->orWhere('headline','like','%'.$filters['q'].'%')->orWhere('skills','like','%'.$filters['q'].'%'));

        $items=[];
        foreach ($query->get() as $candidate) {
            $best=null;
            foreach ($jobs as $job) { $match=$engine->compare((int)$candidate->user_id,(int)$job->job_id); if ($best===null||($match['score']??0)>($best['score']??0)) $best=['jobId'=>(int)$job->job_id,'job'=>$job->title,'jobGrade'=>$job->grade_required,'jobLocation'=>$job->location,...$this->matchPayload($match)]; }
            if ($best===null) continue;
            $assessment=DB::table('assessment_runs')->where('candidate_id',$candidate->user_id)->orderByDesc('completed_at')->first();
            $items[]=[
                'candidateId'=>(int)$candidate->user_id,'name'=>$candidate->username,'headline'=>$candidate->headline,'bio'=>$candidate->bio,'skills'=>$candidate->skills,'city'=>$candidate->city,
                'category'=>$candidate->primary_category,'desiredGrade'=>$candidate->desired_grade,'confirmedGrade'=>$candidate->confirmed_grade,'gradeStatus'=>$candidate->grade_status,
                'salaryExpected'=>$candidate->salary_expected===null?null:(int)$candidate->salary_expected,'workFormat'=>$candidate->work_format,'assessmentScore'=>$assessment?->score===null?null:(float)$assessment->score,
                'integrityScore'=>$assessment?->integrity_score===null?null:(float)$assessment->integrity_score,'confirmedSkills'=>(int)DB::table('candidate_skill_evidence')->where('candidate_id',$candidate->user_id)->where('state','confirmed')->count(),
                'fspEvidence'=>(int)DB::table('fsp_achievements')->where('candidate_id',$candidate->user_id)->where('verified',true)->count(),...$best,
            ];
        }
        $c=collect($items); if(isset($filters['score_min']))$c=$c->filter(fn(array $r)=>(int)($r['score']??0)>=(int)$filters['score_min']); if(isset($filters['assessment_min']))$c=$c->filter(fn(array $r)=>(float)($r['assessmentScore']??0)>=(float)$filters['assessment_min']); if(isset($filters['integrity_min']))$c=$c->filter(fn(array $r)=>(float)($r['integrityScore']??0)>=(float)$filters['integrity_min']);
        return response()->json(['items'=>$this->sortCandidates($c,$filters['sort']??'score_desc')->take(60)->values()->all(),'filters'=>$filters]);
    }

    private function sortCompanies(Collection $items,string $sort):Collection { return match($sort){'salary_desc'=>$items->sortByDesc(fn($r)=>(int)($r['salaryMax']??0)),'quality_desc'=>$items->sortByDesc(fn($r)=>(float)($r['quality']??0)),'response_asc'=>$items->sortBy(fn($r)=>(float)($r['responseHours']??PHP_FLOAT_MAX)),'company_asc'=>$items->sortBy(fn($r)=>mb_strtolower((string)($r['company']??''))),'city_asc'=>$items->sortBy(fn($r)=>mb_strtolower((string)($r['companyCity']??$r['location']??''))),default=>$items->sortByDesc(fn($r)=>(int)($r['score']??0))}; }
    private function sortCandidates(Collection $items,string $sort):Collection { return match($sort){'assessment_desc'=>$items->sortByDesc(fn($r)=>(float)($r['assessmentScore']??0)),'integrity_desc'=>$items->sortByDesc(fn($r)=>(float)($r['integrityScore']??0)),'evidence_desc'=>$items->sortByDesc(fn($r)=>(int)($r['confirmedSkills']??0)),default=>$items->sortByDesc(fn($r)=>(int)($r['score']??0))}; }
    private function matchPayload(array $match):array { $gaps=$match['missingSkills']??[]; if(!($match['salaryOverlap']??false))$gaps[]='зарплатные ожидания'; if(!($match['formatMatch']??false))$gaps[]='формат работы'; if(($match['gradeFit']??0)<.85)$gaps[]='подтверждение уровня'; return ['score'=>(int)($match['score']??0),'route'=>$match['route']??'observe','categoryMatch'=>(bool)($match['categoryMatch']??false),'gradeFit'=>(float)($match['gradeFit']??0),'stackCoverage'=>(float)($match['stackCoverage']??0),'salaryOverlap'=>(bool)($match['salaryOverlap']??false),'formatMatch'=>(bool)($match['formatMatch']??false),'matchedSkills'=>array_values($match['matchedSkills']??[]),'missingSkills'=>array_values($match['missingSkills']??[]),'gaps'=>array_values(array_unique($gaps)),'reasons'=>array_values($match['reasons']??[])]; }
}
