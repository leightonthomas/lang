Toy programming language using a recursive descent parser & [Pratt parsing for expressions](https://journal.stuffwithstuff.com/2011/03/19/pratt-parsers-expression-parsing-made-easy/), with Hindley-Milner type inference (algorithm W)

Compiles to a custom bytecode that is then interpreted.

## Compiling
`src/console.php build {{FILE}} --verbose`

## Disassembling
`src/console.php disassemble {{FILE}} --verbose`

## Running
`src/console.php run {{FILE}} --verbose`

## Testing
`vendor/bin/phpunit`

## Example

```
fn int main() {
    let number = getNumber();

    echo(number - 1 - 2 - 3);

    return number + 3;
}

fn int getNumber() {
    return getLeft() - getRight();
}

fn int getLeft() {
    return (3);
}

fn int getRight() {
    return -4;
}
```
