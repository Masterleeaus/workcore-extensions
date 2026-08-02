<?php
it('declares WorkCore operational authority without AI provider access',function(){ $manifest=require __DIR__.'/../../app/Domains/WorkCore/config/workcore.php'; expect($manifest['authority'])->toBe('WorkCore')->and($manifest['ai_provider_access'])->toBeFalse()->and($manifest['actions'])->toContain('work_order.create','dispatch.assign','attendance.clock_in'); });
