# beschrijving docs app


=algemeen=

docs is een app waarmee gebruikers verschillende soorten bestanden kunnen maken en beheren, compileren of uitvoeren:

Compileren tot pdf:
- latex
- typst
- markdown
- Rmarkdown

uitvoeren:
- R

wijzigen, bekijken:
- json
- txt
-xml
- ...

bekijken:

jpg, png, pdf, ...

== technisch ==

-alles gebeurt met laravel/boost en volgt altijd en overal de laravel regels 
-kies de juiste db structuur en stack
-bestanden worden niet bewaard in de db maar op schijf
-inlggen gebeurt met google login, met test user voor Pest testing (browser testing)
-voor deployment: push naar git@github.com:jvm985/docs.git en vervolgens pull naar docs.irishof.cloud . zorg dat het daar werkt.
op irishof.cloud wordt de juiste ngingx en letesencrypt taken uitgevoerd
- maak Pest test waarin alle features worden getest

== features ==


- gebruikers kunnen projecten delen met andere gebruikers. er verschijnt dan een mooi dialoog scherm waarbij de gebruiker emailadressen kan ingeven, of ook de optie "deel met iedereen". voor iedereen moet kunnen worden aangevinkt "enkel lezen" of "lezen en schrijven"

- de hoofdpagina geeft een overzichtelijke lijst van alle projecten van de user , met apart gedeelde en vor iedereen gedeelde projecten
- wanneer je een project opent, zie je drie panels: links de filetree (volledig functioneel, met toevoegen van bestanden, mappen, verwijderen ervan, uploaden, bestanden verplaatsen door verslepen,...), midden de geschikte editor voor een geopend bestand van het project, rechts de uitvoer (pdf, r-antwoorden, afhankelijk van type bbestand)
- voor alle types: zorg  voor de keyboard shortcut ctrl-enter, om te compileren/runnen

- de bestanden uit andere projecten moeten ook bereikbaar zijn. 
bijvb in latex: bestanden uit eigen project: \include{"templates/cursus.tex"}

== tex bestanden ==
- boven de editor, staat een knop om te compileren, een dropdown om te kiezen tussen pdflatex, xelatex, ...n een knop om de laatse output van de compilere te tonen, rechts pdf

== markdown bestanden ==
- boven de editor, staat een knop om te compileren en een knop om de laatse output van de compilere te tonen, rechts pdf

== Rmarkdown bestanden ==
- boven de editor, staat een knop om te compileren en een knop om de laatse output van de compilere te tonen, rechts pdf

== r-bestanden ==
- het rechtse paneel is in twee gesplitst: bovenaan de r-output van de gerunde code, onderaan een getabd venster met variabelen en plots
- voor R executie: de gebruiker kan een selectie r code uitvoeren, en als er niets is geselecteerd, wordt de regel waar de cursor staat uitgevoerd. 

- voor R: de sessie variabelen moeten enkel die van de gebruiker zijn
- voor R: tussen de runs door, moeten de sessie variabelen behouden blijven
- voor R: toon in de output ook de lijn R code die gezorgd heeft voor de output. zorg voor een andere kleur voor r code en r output zodat het duidelijk leesbaar is.






