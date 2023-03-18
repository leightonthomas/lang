mod lexer;

fn main() {
    let program = "
use Foo.Bar.Baz;

class Foo impl Factory<string> {
    pub fn create(): string {
        return \"foo\";
    }

    fn somePrivateFn(): int {
        return 4;
    }
}

// hello, world!
public fn main(args: Array<string>) {
    let asdf = 42;

    return \"foo\";
}
";

    match lexer::lex(program) {
        Ok(tokens) => println!("{:#?}", tokens),
        Err((pos, msg)) => println!("Error at {:?}: {}", pos, msg),
    }
}
