// examly — een lichtgewicht Typst-pakket voor toetsen, geinspireerd op
// de LaTeX `exam` class. Geen externe afhankelijkheden.
//
// Gebruik:
//   #import "@local/examly:0.1.0": *
//   #show: exam.with(title: "Toets", show-answers: false)
//   #question(points: 2)[Wat is 1+1?]
//   #mc("0", "2", "4", answer: 1)

#let _qcounter = counter("examly-q")
#let _subcounter = counter("examly-sub")
#let _points-state = state("examly-points", ())
#let _show-answers = state("examly-show-answers", false)
#let _show-points = state("examly-show-points", true)
#let _answer-color = rgb("#16a34a")
#let _answer-bg = rgb("#f0fdf4")

// === Top-level configuration ==========================================

#let exam(
  title: "Toets",
  subtitle: none,
  course: none,
  teacher: none,
  date: none,
  duration: none,
  show-answers: false,
  show-points: true,
  show-name-field: true,
  show-points-table: true,
  body,
) = {
  set page(paper: "a4", margin: (x: 2cm, top: 2cm, bottom: 2cm))
  set text(size: 11pt)
  set par(justify: true, leading: 0.65em)

  _show-answers.update(show-answers)
  _show-points.update(show-points)

  // Header
  align(center)[
    #text(size: 18pt, weight: "bold")[#title]
    #if subtitle != none { linebreak(); text(size: 13pt)[#subtitle] }
  ]

  // metadata row
  if course != none or teacher != none or date != none or duration != none {
    v(0.4em)
    let cells = ()
    if course != none { cells.push([*Vak:* #course]) }
    if teacher != none { cells.push([*Lkr:* #teacher]) }
    if date != none { cells.push([*Datum:* #date]) }
    if duration != none { cells.push([*Duur:* #duration]) }
    align(center, cells.join(h(1.5em)))
  }

  v(0.4em)
  line(length: 100%, stroke: 0.5pt + gray)
  v(0.6em)

  if show-name-field {
    grid(
      columns: (1fr, auto, 1fr),
      column-gutter: 1em,
      [*Naam:* #box(width: 100%, repeat[.])],
      [],
      [*Klas:* #box(width: 100%, repeat[.])],
    )
    v(0.6em)
  }

  if show-answers {
    align(center, rect(
      fill: rgb("#fef3c7"),
      stroke: 0.5pt + rgb("#d97706"),
      inset: 0.4em,
      radius: 4pt,
      text(size: 9pt, weight: "bold")[CORRECTIESLEUTEL — antwoorden zichtbaar],
    ))
    v(0.4em)
  }

  body

  // points table at end
  if show-points-table and show-points {
    pagebreak(weak: true)
    points-table()
  }
}

// === Question primitives ==============================================

// Top-level question. Pass points (default 0) and the question content.
#let question(points: 0, body) = {
  _qcounter.step()
  _subcounter.update(0)
  context {
    let n = _qcounter.get().first()
    let show-pts = _show-points.get()
    block(below: 0.5em, above: 1em)[
      #grid(
        columns: (auto, 1fr, auto),
        column-gutter: 0.4em,
        text(weight: "bold")[#n.],
        body,
        if show-pts and points > 0 {
          text(fill: gray, size: 9pt)[(#points pt)]
        } else { [] },
      )
    ]
  }
  // Record points for the table at the end.
  _points-state.update(arr => arr + ((number: arr.len() + 1, points: points),))
}

// A sub-question (a, b, c, ...) inside a question.
#let subquestion(points: 0, body) = {
  _subcounter.step()
  context {
    let s = _subcounter.get().first()
    let letter = ("a","b","c","d","e","f","g","h","i","j").at(s - 1, default: str(s))
    let show-pts = _show-points.get()
    block(below: 0.4em, inset: (left: 1.2em))[
      #grid(
        columns: (auto, 1fr, auto),
        column-gutter: 0.4em,
        text(weight: "bold")[#letter)],
        body,
        if show-pts and points > 0 {
          text(fill: gray, size: 9pt)[(#points pt)]
        } else { [] },
      )
    ]
  }
}

// === Answer presentation ==============================================

// Multiple choice. Pass options as positional args; answer = index of correct
// option (0-based). For multiple correct: pass answer: (0, 2).
#let mc(..options, answer: none) = context {
  let show = _show-answers.get()
  let opts = options.pos()
  let correct = if type(answer) == array { answer } else if answer != none { (answer,) } else { () }
  block(inset: (left: 1.5em), above: 0.4em)[
    #for (i, o) in opts.enumerate() {
      let letter = ("A","B","C","D","E","F","G","H").at(i, default: str(i + 1))
      let is-correct = i in correct
      let mark = if show and is-correct { "■" } else { "□" }
      let row = [#mark #letter. #o]
      block(below: 0.25em, if show and is-correct { text(fill: _answer-color)[#row] } else { row })
    }
  ]
}

// Multi-select (checkboxes) — same as mc but answer is always a list.
#let select(..options, answers: ()) = mc(..options, answer: answers)

// True / false (Waar / Niet waar).
#let truefalse(answer: none) = context {
  let show = _show-answers.get()
  let mw = if show and answer == true { "■" } else { "□" }
  let mn = if show and answer == false { "■" } else { "□" }
  let label-w = if show and answer == true { text(fill: _answer-color)[#mw Waar] } else [#mw Waar]
  let label-n = if show and answer == false { text(fill: _answer-color)[#mn Niet waar] } else [#mn Niet waar]
  block(inset: (left: 1.5em), above: 0.4em)[
    #label-w #h(2em) #label-n
  ]
}

// Lined area for a free-form answer; shows the model answer when in
// correctie-modus, otherwise N empty lines.
#let lines(n: 3, answer: none) = context {
  let show = _show-answers.get()
  if show and answer != none {
    block(
      inset: 0.5em,
      stroke: 0.5pt + _answer-color,
      fill: _answer-bg,
      width: 100%,
      above: 0.4em,
    )[#answer]
  } else {
    v(0.3em)
    for _ in range(n) {
      line(length: 100%, stroke: 0.5pt + gray)
      v(0.6em)
    }
  }
}

// Single-line short answer (one or two ruler lines).
#let short(answer: none) = lines(n: 1, answer: answer)

// Fill-in-the-blanks. Pass a string with `{antwoord}` markers, e.g.
//   #fillin("De hoofdstad is {Brussel} en de munt is de {euro}.")
// In normal mode the gaps render as ____; with show-answers the answer
// is shown in green.
#let fillin(text-str) = context {
  let show = _show-answers.get()
  let parts = text-str.split(regex("(\{[^\}]*\})"))
  let result = []
  for p in parts {
    if p.starts-with("{") and p.ends-with("}") {
      let ans = p.slice(1, p.len() - 1)
      if show {
        result += text(fill: _answer-color, weight: "bold")[ #underline(ans) ]
      } else {
        let w = calc.max(2em, 0.6em * ans.len())
        result += box(width: w, baseline: 30%, line(length: 100%, stroke: 0.5pt))
      }
    } else {
      result += [#p]
    }
  }
  block(inset: (left: 1.5em), above: 0.4em, result)
}

// Matching pairs. Pass a list of (left, right) tuples.
//   #match(
//     ("Brussel", "België"),
//     ("Parijs", "Frankrijk"),
//   )
// Without show-answers: left column on left, scrambled right column on right.
// With show-answers: arrows showing the correct mapping.
#let match(..pairs, scramble: true) = context {
  let show = _show-answers.get()
  let lst = pairs.pos()
  let lefts = lst.map(p => p.at(0))
  let rights = lst.map(p => p.at(1))
  let display-rights = if show or not scramble {
    rights
  } else {
    // simple deterministic shuffle by reversing
    rights.rev()
  }
  block(inset: (left: 1.5em), above: 0.4em)[
    #grid(
      columns: (1fr, auto, 1fr),
      column-gutter: 1em,
      row-gutter: 0.6em,
      ..for (i, l) in lefts.enumerate() {
        (
          [#str(i + 1). #l],
          if show { [→] } else { [#sym.dots.h.c] },
          if show {
            text(fill: _answer-color, weight: "bold")[#display-rights.at(i)]
          } else {
            display-rights.at(i)
          },
        )
      }
    )
  ]
}

// === Points table =====================================================

#let points-table() = context {
  let entries = _points-state.final()
  if entries.len() == 0 { return }
  let total = entries.fold(0, (sum, e) => sum + e.points)
  align(center)[
    #text(weight: "bold", size: 12pt)[Puntenoverzicht]
    #v(0.4em)
    #table(
      columns: (auto,) + (auto,) * entries.len() + (auto,),
      align: center + horizon,
      stroke: 0.5pt,
      fill: (col, row) => if row == 0 { rgb("#f3f4f6") } else { white },
      [Vraag], ..entries.map(e => [*#e.number*]), [*Totaal*],
      [Punten], ..entries.map(e => [#e.points]), [*#total*],
      [Behaald], ..entries.map(_ => []), [],
    )
  ]
}

// Inline total (points-state sum) — useful for the cover or footer.
#let total-points() = context {
  let entries = _points-state.final()
  let total = entries.fold(0, (sum, e) => sum + e.points)
  [#total]
}

// === Question bank ====================================================

// Render a single question entry from a bank dict (e.g. loaded via
// #json("bank.json")). Supported types: mc, multi, tf, short, lines,
// fillin, match, open. Each entry has at least { type, text, points }.
#let from-bank(q) = {
  let pts = q.at("points", default: 0)
  question(points: pts)[#q.at("text")]
  let t = q.at("type", default: "open")
  if t == "mc" {
    mc(..q.at("options"), answer: q.at("answer", default: none))
  } else if t == "multi" {
    select(..q.at("options"), answers: q.at("answers", default: ()))
  } else if t == "tf" {
    truefalse(answer: q.at("answer", default: none))
  } else if t == "short" {
    short(answer: q.at("answer", default: none))
  } else if t == "lines" {
    lines(n: q.at("lines", default: 3), answer: q.at("answer", default: none))
  } else if t == "fillin" {
    fillin(q.at("text-with-gaps", default: q.at("text")))
  } else if t == "match" {
    match(..q.at("pairs").map(p => (p.at(0), p.at(1))))
  } else {
    // open: just lines
    lines(n: q.at("lines", default: 5), answer: q.at("answer", default: none))
  }
}

// Pick a subset of a bank by ids (preserves bank order).
#let pick(bank, ids) = bank.filter(q => q.at("id", default: "") in ids)

// Render a whole bank (or subset) sequentially.
#let bank(qs) = {
  for q in qs { from-bank(q) }
}
