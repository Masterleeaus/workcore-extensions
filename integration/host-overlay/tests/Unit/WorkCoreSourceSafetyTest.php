<?php
it('keeps queued operational runtime free of request globals',function(){ $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../../app/Domains/WorkCore')); foreach($files as $file){ if(!$file->isFile()||$file->getExtension()!=='php') continue; $text=file_get_contents($file->getPathname()); expect($text)->not->toContain('auth()')->not->toContain('session()'); } });
