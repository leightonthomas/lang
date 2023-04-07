use std::fmt::Display;

#[derive(Debug, PartialEq, Eq)]
pub enum Keyword {
    Function,
    Return,
    Let,
    Class,
    Implements,
    Public,
    Protected,
    Abstract,
    Static,
    Use,
}
impl Keyword {
    pub fn from_string(str: &str) -> Option<Keyword> {
        match str {
            "fn" => Some(Keyword::Function),
            "return" => Some(Keyword::Return),
            "let" => Some(Keyword::Let),
            "class" => Some(Keyword::Class),
            "impl" => Some(Keyword::Implements),
            "public" => Some(Keyword::Public),
            "protected" => Some(Keyword::Protected),
            "abstract" => Some(Keyword::Abstract),
            "static" => Some(Keyword::Static),
            "use" => Some(Keyword::Use),
            _ => None,
        }
    }
}

#[derive(Debug, PartialEq, Eq, Clone, Copy)]
pub enum Symbol {
    Equal = '=' as isize,
    ParenOpen = '(' as isize,
    ParenClose = ')' as isize,
    BraceOpen = '{' as isize,
    BraceClose = '}' as isize,
    BracketOpen = '[' as isize,
    BracketClose = ']' as isize,
    AngleOpen = '<' as isize,
    AngleClose = '>' as isize,
    Comma = ',' as isize,
    Period = '.' as isize,
    Colon = ':' as isize,
    Plus = '+' as isize,
    Minus = '-' as isize,
    ForwardSlash = '/' as isize,
    Exclamation = '!' as isize,
    Question = '?' as isize,
    Asterisk = '*' as isize,
    Caret = '^' as isize,
}
impl Symbol {
    pub fn from_char(char: &u8) -> Option<Symbol> {
        match char {
            &b'=' => Some(Symbol::Equal),
            &b'(' => Some(Symbol::ParenOpen),
            &b')' => Some(Symbol::ParenClose),
            &b'{' => Some(Symbol::BraceOpen),
            &b'}' => Some(Symbol::BraceClose),
            &b'[' => Some(Symbol::BracketOpen),
            &b']' => Some(Symbol::BracketClose),
            &b'<' => Some(Symbol::AngleOpen),
            &b'>' => Some(Symbol::AngleClose),
            &b',' => Some(Symbol::Comma),
            &b'.' => Some(Symbol::Period),
            &b':' => Some(Symbol::Colon),
            &b'+' => Some(Symbol::Plus),
            &b'-' => Some(Symbol::Minus),
            &b'/' => Some(Symbol::ForwardSlash),
            &b'!' => Some(Symbol::Exclamation),
            &b'?' => Some(Symbol::Question),
            &b'*' => Some(Symbol::Asterisk),
            &b'^' => Some(Symbol::Caret),
            _ => None,
        }
    }
}

impl Display for Symbol {
    fn fmt(&self, f: &mut std::fmt::Formatter<'_>) -> std::fmt::Result {
        write!(f, "{}", *self as isize as u8 as char)
    }
}

#[derive(Debug, PartialEq, Eq)]
pub enum Token {
    Comment(String),
    Keyword(Keyword),
    StringLiteral(String),
    IntegerLiteral(i64),
    Identifier(String),
    Symbol(Symbol),
    /// Denotes the end of a statement
    End,
}

