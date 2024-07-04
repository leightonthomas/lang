Toy programming language using a recursive descent parser & [Pratt parsing for expressions](https://journal.stuffwithstuff.com/2011/03/19/pratt-parsers-expression-parsing-made-easy/), with Hindley-Milner type inference (algorithm W)

Compiles to a custom bytecode that is then interpreted.

## Compiling
`src/console.php build {{FILE}} --verbose`

## Running
`src/console.php run {{FILE}} --verbose`

## Testing
`vendor/bin/phpunit`

## Example

```
// some comment
fn int main() {
    let foo = -1 + 4; // 3

    echo(foo);

    return foo - 1;
}
```
