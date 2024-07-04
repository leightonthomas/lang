Toy programming language using a recursive descent parser & [Pratt parsing for expressions](https://journal.stuffwithstuff.com/2011/03/19/pratt-parsers-expression-parsing-made-easy/), with Hindley-Milner type inference (algorithm W)

Compiles to a custom bytecode that is then interpreted.

## Compiling
`just build {{file}}`

## Running
`just run {{file}}`

## Testing
`just test`

## Example

```
// some comment
fn int main() {
    let foo = 4 - 7;

    return getOther() + (foo - 1);
}

fn int getOther() {
    return 5;
}
```
