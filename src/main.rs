mod lexer;
mod parser;

fn main() {
//     let program = "
// use Foo.Bar.Baz;
//
// public class Foo impl Factory<string> {
//     pub fn create(): string {
//         return \"foo\";
//     }
//
//     fn somePrivateFn(): int {
//         let foo = fn(): Bar => baz;
//         foo();
//
//         let foo2 = fn(): Bar => {
//             return baz;
//         };
//
//         return 4;
//     }
// }
//
// // hello, world!
// public fn main(args: Array<string>) {
//     let asdf = {
//         return 42;
//     };
//
//     return \"foo\";
// }
// ";
    let program = "
use Foo.Bar.Baz;use Fizz.Buzz;
use Fizz;

public fn main(foo: Bar, baz: Int): Baz {
    let abc: Int = -4 + (7 - 3 + asdf - hjkl(7, 8, abc));

    helloWorld(6, 7, 8);

    return 42;
}
";

    match lexer::lex(program) {
        Ok((tokens, code_blocks)) => match parser::parse_file(&tokens, code_blocks, &"Test.Namespace") {
            Ok(file) => println!("Parse success: {:#?}", file),
            Err(err) => println!("Error at {:?}: {}", err.span, err.message),
        },
        Err((pos, msg)) => println!("Error at {:?}: {}", pos, msg),
    }
}

