@extends('panel.layout.app')
@section('title',$wizard['title'])
@section('content')
<div class="container-xl py-6" x-data="workcoreWizard(@js($wizard), @js(url(config('workcore.wizards.api_prefix','api/workcore/v1'))))" x-init="boot()">
  <div class="mx-auto max-w-5xl">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
      <div>
        <div class="text-sm text-muted">{{ $wizard['category'] ?? 'wizard' }}</div>
        <h1 class="text-3xl font-semibold">{{ $wizard['title'] }}</h1>
        <p class="text-muted">{{ $wizard['description'] }}</p>
      </div>
      <div class="flex items-center gap-2">
        <div class="flex rounded-xl border p-1">
          <button @click="mode='conversation'" :class="mode==='conversation'&&'bg-primary text-white'" class="rounded-lg px-3 py-2">Ask Titan</button>
          <button @click="mode='guided'" :class="mode==='guided'&&'bg-primary text-white'" class="rounded-lg px-3 py-2">Guided setup</button>
        </div>
        <button @click="pause()" class="rounded-xl border px-3 py-2" x-show="runId && status==='in_progress'">Pause</button>
      </div>
    </div>

    <div class="h-2 overflow-hidden rounded-full bg-muted"><div class="h-full bg-primary transition-all" :style="`width:${progress.percent||0}%`"></div></div>
    <div class="mt-2 flex justify-between text-xs text-muted"><span x-text="statusLabel"></span><span x-text="`${progress.answered||0} of ${progress.total||0} answered`"></span></div>

    <div class="mt-5 rounded-2xl border bg-card p-6" x-show="loading"><div class="animate-pulse">Preparing the next step…</div></div>
    <div class="mt-5 rounded-2xl border border-red-300 bg-red-50 p-4 text-red-800" x-show="error" x-text="error"></div>

    <main class="mt-5 rounded-2xl border bg-card p-6" x-show="!loading && question">
      <div class="text-sm text-muted" x-text="section?.title"></div>
      <div class="mt-3 flex items-start justify-between gap-4">
        <h2 class="text-2xl font-semibold" x-text="question?.question"></h2>
        <span class="rounded-full border px-2 py-1 text-xs" x-text="question?.risk||'low'"></span>
      </div>
      <p class="mt-2 text-sm text-muted" x-show="question?.notes" x-text="question?.notes"></p>

      <div class="mt-6">
        <textarea x-show="!['boolean','select','multi_select'].includes(question?.response_type)" x-model="answer" class="min-h-32 w-full rounded-xl border p-3" placeholder="Type your answer or tell Titan…"></textarea>
        <select x-show="question?.response_type==='select'" x-model="answer" class="w-full rounded-xl border p-3"><option value="">Select…</option><template x-for="option in question?.options||[]"><option :value="option" x-text="option"></option></template></select>
        <div x-show="question?.response_type==='boolean'" class="flex gap-3"><button @click="answer=true" class="rounded-xl border px-4 py-3" :class="answer===true&&'bg-primary text-white'">Yes</button><button @click="answer=false" class="rounded-xl border px-4 py-3" :class="answer===false&&'bg-primary text-white'">No</button></div>
      </div>

      <div class="mt-5 rounded-xl border p-4" x-show="mode==='conversation'">
        <strong>Titan:</strong> <span x-text="question?.question"></span>
        <div class="mt-2 text-sm text-muted">Titan can extract and save a low-risk draft. Medium- and high-risk answers remain unconfirmed until you approve their section.</div>
      </div>

      <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
        <div class="text-xs text-muted">Answer source: <span x-text="mode==='conversation'?'ai_extracted':'user_entered'"></span></div>
        <button @click="saveAnswer()" :disabled="saving" class="rounded-xl bg-primary px-5 py-2 text-white disabled:opacity-50"><span x-text="saving?'Saving…':'Save and continue'"></span></button>
      </div>
    </main>

    <main class="mt-5 rounded-2xl border bg-card p-6" x-show="!loading && !question && readyForReview">
      <h2 class="text-2xl font-semibold">Review and approve</h2>
      <p class="mt-2 text-muted">Each section shows what Titan understood. Approval applies only to that visible section.</p>
      <div class="mt-5 space-y-4">
        <template x-for="sectionReview in reviewSections" :key="sectionReview.key">
          <div class="rounded-xl border p-4">
            <div class="flex items-center justify-between gap-3"><div><h3 class="font-semibold" x-text="sectionReview.title"></h3><div class="text-xs text-muted" x-text="`${sectionReview.answers.length} answers · ${sectionReview.approval_decision}`"></div></div><span class="rounded-full border px-2 py-1 text-xs" x-text="sectionReview.approved?'Approved':sectionReview.risk"></span></div>
            <dl class="mt-3 grid gap-2 text-sm"><template x-for="item in sectionReview.answers" :key="item.key"><div class="grid gap-1 md:grid-cols-[1fr_1fr]"><dt class="text-muted" x-text="item.question"></dt><dd class="break-words" x-text="formatValue(item.value)"></dd></div></template></dl>
            <button x-show="!sectionReview.approved && sectionReview.approval_decision!=='auto_draft'" @click="approveSection(sectionReview)" class="mt-4 rounded-xl border px-4 py-2">Approve this section</button>
          </div>
        </template>
      </div>
      <div class="mt-6 flex justify-end"><button @click="complete()" class="rounded-xl bg-primary px-5 py-2 text-white">Complete wizard</button></div>
    </main>

    <main class="mt-5 rounded-2xl border bg-card p-8 text-center" x-show="status==='completed'">
      <h2 class="text-2xl font-semibold">Wizard completed</h2>
      <p class="mt-2 text-muted">The approved answers are preserved with provenance. Canonical WorkCore domains receive a draft action plan rather than direct ungoverned writes.</p>
      <a href="{{ route('workcore.wizards.index') }}" class="mt-5 inline-block rounded-xl border px-5 py-2">Back to all wizards</a>
    </main>
  </div>
</div>
<script>
function workcoreWizard(wizard, apiBase){return{
  wizard,apiBase,mode:'hybrid',runId:null,status:'new',question:null,section:null,answer:'',progress:{answered:0,total:0,percent:0},readyForReview:false,reviewSections:[],loading:true,saving:false,error:'',
  get statusLabel(){return this.status==='paused'?'Paused — your answers are saved':this.status==='completed'?'Completed':this.mode==='conversation'?'Titan is guiding this wizard':'Guided setup'},
  async boot(){try{this.mode=localStorage.getItem(`workcore-wizard-mode:${this.wizard.key}`)||'hybrid';this.runId=localStorage.getItem(`workcore-wizard-run:${this.wizard.key}`);if(!this.runId)await this.start();else await this.loadNext()}catch(e){this.fail(e)}finally{this.loading=false}},
  async start(){let data=await this.request('/wizards/runs',{method:'POST',body:{definition_key:this.wizard.key,mode:this.mode}});this.runId=data.public_id;this.status=data.status;localStorage.setItem(`workcore-wizard-run:${this.wizard.key}`,this.runId);await this.loadNext()},
  async loadNext(){this.loading=true;let data=await this.request(`/wizards/runs/${this.runId}/next`);this.status=data.run?.status||this.status;this.question=data.question;this.section=data.section||null;this.progress=data.progress||this.progress;this.readyForReview=!!data.ready_for_review;this.answer='';if(this.readyForReview)await this.loadReview();this.loading=false},
  async loadReview(){let data=await this.request(`/wizards/runs/${this.runId}`);this.reviewSections=data.sections||[];this.status=data.run?.status||this.status},
  async saveAnswer(){if(!this.question)return;this.saving=true;this.error='';try{await this.request(`/wizards/runs/${this.runId}/answer`,{method:'PUT',body:{question_key:this.question.key,value:this.answer,source:this.mode==='conversation'?'ai_extracted':'user_entered',confidence:this.mode==='conversation'?0.8:null,is_confirmed:this.mode!=='conversation'||this.question.risk==='low'}});await this.loadNext()}catch(e){this.fail(e)}finally{this.saving=false}},
  async approveSection(section){try{await this.request(`/wizards/runs/${this.runId}/approve`,{method:'POST',confirmation:true,body:{section_key:section.key,summary:section}});await this.loadReview()}catch(e){this.fail(e)}},
  async pause(){try{let data=await this.request(`/wizards/runs/${this.runId}/pause`,{method:'POST'});this.status=data.status}catch(e){this.fail(e)}},
  async complete(){try{let data=await this.request(`/wizards/runs/${this.runId}/complete`,{method:'POST',confirmation:true});this.status=data.status;if(this.status==='completed')localStorage.removeItem(`workcore-wizard-run:${this.wizard.key}`)}catch(e){this.fail(e)}},
  async request(path,options={}){let headers={'Accept':'application/json','Content-Type':'application/json','Idempotency-Key':crypto.randomUUID()};if(options.confirmation)headers['X-Confirmation-Id']=crypto.randomUUID();let response=await fetch(this.apiBase+path,{method:options.method||'GET',headers,credentials:'same-origin',body:options.body?JSON.stringify(options.body):undefined});let payload=await response.json();if(!response.ok)throw new Error(payload?.message||payload?.errors?.[0]?.detail||'Wizard request failed.');return payload.data},
  formatValue(value){if(value===null||value===undefined||value==='')return 'Not answered';return typeof value==='object'?JSON.stringify(value):String(value)},
  fail(error){this.error=error?.message||String(error);this.loading=false}
}}
</script>
@endsection
