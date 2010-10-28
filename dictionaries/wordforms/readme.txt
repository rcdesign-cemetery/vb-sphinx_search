Helper tools to generate wordforms file

1. ispell_dump.sh
-----------------

Dumps ispell dictionsries to current directory and convert those to unicode.
Normally you don't need it, because dumped files already exists in repo (eng & ru)

Prior to run this script, you need:

a) ispell installed to your computer
b) sphinx installed (spelldump utility)

2. merge_wordworms.sh
---------------------

Merge all wf_*.zip files into single one. Because sphinx requires 1 file in config.
