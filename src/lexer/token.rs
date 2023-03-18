#[derive(Debug)]
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

#[derive(Debug)]
pub enum Symbol {
    Equal,
    ParenOpen,
    ParenClose,
    BraceOpen,
    BraceClose,
    BracketOpen,
    BracketClose,
    AngleOpen,
    AngleClose,
    Comma,
    Period,
    Colon,
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
            _ => None,
        }
    }
}

#[derive(Debug)]
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
