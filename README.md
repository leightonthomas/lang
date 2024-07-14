Statically typed toy programming language using a recursive descent parser & [Pratt parsing for expressions](https://journal.stuffwithstuff.com/2011/03/19/pratt-parsers-expression-parsing-made-easy/), with Hindley-Milner type inference (algorithm W)

Compiles to a custom bytecode that is then interpreted

## Setup
`composer install`

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
// some comment
fn int main() {
    let number = getNumber();
    if (! (number < 5)) {
        echo("hello");
    }
    
    if (number == 6) {
        echo(", world!");
    }
    
    {
        number = number - {
            return 1;
        };
    };
    
    while (number < 10) {
        number = number + 1;
        
        if (number == 9) {
            break;
        }
    }

    // 12
    return {
        let someOtherNumber = { return 3; };

        return number + someOtherNumber;
    };
}

fn int getNumber() {
    return getLeft(1) - getRight();
}

fn int getLeft(int minus) {
    return (3) - minus;
}

fn int getRight() {
    return -4;
}

fn unit noReturnFunction() {

}

```
