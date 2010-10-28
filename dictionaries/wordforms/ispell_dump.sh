#!/bin/bash

# convert russian dictionary
spelldump /usr/share/hunspell/ru_RU.dic /usr/share/hunspell/ru_RU.aff wf_ru.txt
recode KOI8-R..utf8 wf_ru.txt
gzip -c wf_ru.txt>wf_ru.gz
rm wf_ru.txt

# convert english dictionary
spelldump /usr/share/hunspell/en_US.dic /usr/share/hunspell/en_US.aff wf_en.txt
recode ISO-8859-1..utf8 wf_en.txt
gzip -c wf_en.txt>wf_en.gz
rm wf_en.txt
