# Change Log

## [2022.05.14]

- `DB::__call()` : Defined for `findBy*()` methods.
- Added `DB::setParameter()` method.
- Created the Helper class to handle PHP functions only.

## [2022.05.10]

- [Model] Added `relations()` method for easier linking between models.

## [2022.05.08]

- [QueryBuilder] Added methods : 
  - `selectDistinct()`
  - `selectCoalesce()`
  - `regexp()`, `andRegexp()` and `orRegexp()`
  - `selectMin()`, `selectMax()`, `selectAvg()`, `selectUpper()`, `selectLower()`, `selectLength()`, `selectMid()`, `selectLeft()`, `selectRight()`