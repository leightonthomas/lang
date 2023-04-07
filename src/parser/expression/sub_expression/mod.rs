use crate::lexer::{get_next_token, SourceToken, token_is};
use crate::lexer::token::{Symbol, Token};
use crate::parser::{Failure, File};
use crate::parser::statement::{SubExpression, Prefix, Infix};

#[derive(Clone, PartialEq, Eq, PartialOrd, Ord)]
pub enum Precedence {
    Default = 0,
    Sum = 3,
    Product = 4,
    Prefix = 6,
    Call = 8,
}

/// Parse a [SubExpression].
///
/// ## Arguments
/// * `file` - the file context
/// * `tokens` - the whole set of tokens for the file
/// * `starting_index` - the index of the keyword
/// * `starting_token` - the token for the keyword
///
/// ## Returns
/// A [Result] containing the sub-expression and the new index into the tokens, or a failure.
pub fn parse_sub_expression(
    file: &File,
    tokens: &Vec<SourceToken>,
    starting_index: &usize,
    starting_token: &SourceToken,
    precedence: Precedence,
) -> Result<(SubExpression, usize), Failure> {
    let mut next_index = starting_index.clone() + 1;
    let mut previous_token: &SourceToken = starting_token;

    let (next_token, _) = get_next_token(
        tokens,
        next_index,
        previous_token,
        |n| Ok(n),
        "Expected a sub-expression.".to_owned(),
    )?;

    // http://journal.stuffwithstuff.com/2011/03/19/pratt-parsers-expression-parsing-made-easy/

    // parse prefix; next_index should be updated to the next token - 1
    let mut left = match &next_token.token {
        Token::Identifier(id) => SubExpression::Variable(id.to_owned()),
        Token::StringLiteral(str) => SubExpression::StringLiteral(str.to_owned()),
        Token::IntegerLiteral(int) => SubExpression::IntegerLiteral(int.to_owned()),
        Token::Symbol(sym) => {
            let (rhs, new_next_index) = parse_sub_expression(
                &file, 
                &tokens, 
                &next_index, 
                &next_token, 
                match sym {
                    Symbol::ParenOpen => Precedence::Default,
                    _ => Precedence::Prefix,
                },
            )?;

            // normalise this so that after the prefix we can just
            // add one to get the actual next index, since for
            // non-symbols we'll still be pointing to this token
            next_index = new_next_index - 1; 

            SubExpression::Prefix(
                match sym {
                    Symbol::Plus => Prefix::Plus(Box::new(rhs)),
                    Symbol::Minus => Prefix::Minus(Box::new(rhs)),
                    Symbol::Exclamation => Prefix::Not(Box::new(rhs)),
                    Symbol::ParenOpen => {
                        next_index += 1;

                        Prefix::Group(Box::new(rhs))
                    },
                    _ => return Err(Failure { message: format!("Unknown symbol '{}'.", sym).to_owned(), span: next_token.span.clone() }),
                },
            )
        },
        _ => return Err(Failure { message: "Expected valid sub-expression.".to_owned(), span: next_token.span.clone() }),
    };
    
    next_index += 1;
    previous_token = &tokens[next_index - 1];

    loop {
        let (infix_token, _) = get_next_token(&tokens, next_index, &previous_token, |n| Ok(n), "".to_owned())?;
        let infix_precedence = get_precedence(&infix_token.token);

        if precedence >= infix_precedence {
            return Ok((left, next_index));
        }

        left = match &infix_token.token {
            Token::Symbol(Symbol::ParenClose) => return Ok((left, next_index + 1)),
            Token::Symbol(Symbol::ParenOpen) => {
                let prev_token = &tokens[next_index - 1];
                if let Token::Identifier(id) = &prev_token.token {
                    let mut arguments: Vec<SubExpression> = Vec::new();
                    
                    loop {
                        let (rhs, new_next_index) = parse_sub_expression(
                            &file, 
                            &tokens, 
                            &next_index, 
                            &next_token,
                            Precedence::Default,
                        )?;

                        next_index = new_next_index;
                        arguments.push(rhs);
                        
                        if ! token_is(&Token::Symbol(Symbol::Comma), &tokens, next_index.clone()) {
                            break;
                        }
                    }

                    if ! token_is(&Token::Symbol(Symbol::ParenClose), &tokens, next_index.clone()) {
                        return Err(
                            Failure { message: "Expected closing parenthesis.".to_owned(), span: tokens[next_index].span.clone() },
                        );
                    }

                    next_index += 1;

                    SubExpression::FunctionCall(id.to_owned(), arguments)
                } else {
                    return Err(
                        Failure { message: "Cannot call function on non-identifier.".to_owned(), span: prev_token.span.clone() },
                    );
                }
            },
            Token::Symbol(sym) => {
                let (rhs, new_next_index) = parse_sub_expression(
                    &file, 
                    &tokens, 
                    &next_index, 
                    &next_token,
                    infix_precedence,
                )?;

                next_index = new_next_index;

                SubExpression::Infix(
                    match sym {
                        Symbol::Plus => Infix::Add(Box::new(left), Box::new(rhs)),
                        Symbol::Minus => Infix::Sub(Box::new(left), Box::new(rhs)),
                        _ => return Err(Failure { message: format!("Unknown symbol '{}'.", sym).to_owned(), span: infix_token.span.clone() }),
                    }
                )
            },
            _ => return Ok((left, next_index)),
        }
    }
}

fn get_precedence(token: &Token) -> Precedence {
    match token {
        Token::IntegerLiteral(_) | Token::StringLiteral(_) | Token::Identifier(_) => Precedence::Default,
        Token::End | Token::Comment(_) | Token::Keyword(_) => Precedence::Default,
        Token::Symbol(sym) => match sym {
            Symbol::Plus | Symbol::Minus => Precedence::Sum,
            Symbol::ForwardSlash | Symbol::Asterisk => Precedence::Product,
            Symbol::ParenOpen => Precedence::Call,
            _ => Precedence::Default,
        },
    }
}
