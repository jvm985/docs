<?php

use App\Models\Project;
use App\Models\File;

$project = Project::where('name', '05 geschiedenis typst')->first();
$file = $project->files()->where('name', 'template.typ')->first();

$file->content = '
#let leerstof(title: "", body) = block(
  fill: red.lighten(95%),
  stroke: (left: 3pt + red),
  inset: 12pt,
  width: 100%,
  breakable: true,
  [
    #text(weight: "bold", fill: red.darken(20%), size: 9pt)[#smallcaps("Leerstof: ") #title]
    #v(4pt)
    #body
  ]
)

#let bron(title: "", body) = block(
  fill: blue.lighten(95%),
  stroke: (left: 3pt + blue),
  inset: 12pt,
  width: 100%,
  breakable: true,
  [
    #text(weight: "bold", fill: blue.darken(20%), size: 9pt)[#smallcaps("Bron: ") #title]
    #v(4pt)
    #set text(style: "italic")
    #body
  ]
)

#let source(title: "", body) = block(
  fill: luma(245),
  stroke: (left: 3pt + luma(50)),
  inset: 12pt,
  width: 100%,
  breakable: true,
  [
    #text(weight: "bold", fill: luma(50), size: 9pt)[#smallcaps("Bron: ") #title]
    #v(4pt)
    #body
  ]
)

#let opdracht(body) = block(
  fill: gray.lighten(95%),
  stroke: 0.5pt + gray,
  inset: 12pt,
  radius: 2pt,
  width: 100%,
  breakable: true,
  [
    #text(weight: "bold", fill: gray.darken(20%), size: 9pt)[#smallcaps("Opdracht")]
    #v(4pt)
    #body
  ]
)

#let kunnen_en_kennen(je_kan, jargon, begrippen) = block(
  width: 100%,
  inset: (top: 1em),
  [
    == Kunnen en kennen:
    #grid(
      columns: (1fr, 1fr),
      gutter: 2em,
      [
        *Je kan:*
        #je_kan
      ],
      [
        *Jargon:*
        #jargon
        #v(1em)
        *Historische begrippen:*
        #begrippen
      ]
    )
  ]
)

#let project(title: "", authors: (), body) = {
  set document(author: authors, title: title)
  set page(
    paper: "a4",
    margin: (x: 2.5cm, y: 2.5cm),
    header: context {
      if counter(page).get().first() > 1 [
        #set text(8pt, style: "italic")
        #title 
        #h(1fr) 
        Pagina #counter(page).display()
      ]
    },
    numbering: "1",
  )
  
  set text(font: "Libertinus Serif", size: 11pt, lang: "nl")
  set heading(numbering: "1.1")
  set par(justify: true, leading: 0.65em)
  
  show heading: it => block(below: 1em, above: 1.5em)[
    #if it.level == 1 {
      set text(20pt, weight: "bold", fill: navy)
      it
    } else {
      it
    }
  ]

  // Improve image spacing
  show figure: set block(spacing: 1.5em)

  body
}
';
$file->save();
echo "Updated template.typ with source and goals support\n";
