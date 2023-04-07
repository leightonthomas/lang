use std::collections::HashMap;
use std::rc::Rc;
use crate::lexer::{get_next_token, SourceToken, Span};
use crate::lexer::token::{Keyword, Symbol, Token};
use crate::parser::{Failure, File};
use crate::parser::arguments::parse_arguments;
use crate::parser::expression::parse_expression;
use crate::parser::resolved_type::parse_resolved_type;
use crate::parser::statement::{as_type_reference, DefinedFunction, Expression, Function, ResolvedType, Visibility};

/// Parse a function declaration.
///
/// ## Arguments
/// * `file` - the file context that we'll be adding the function to
/// * `tokens` - the whole set of tokens for the file
/// * `starting_index` - the index of the keyword
/// * `starting_token` - the token for the keyword
///
/// ## Returns
/// A [Result] containing the new index into the tokens after parsing the function, or a
/// failure.
pub fn parse_function(
    file: &mut File,
    tokens: &Vec<SourceToken>,
    starting_index: &usize,
    starting_token: &SourceToken,
) -> Result<usize, Failure> {
    let mut next_index = starting_index.clone() + 1;
    let mut previous_token: &SourceToken = starting_token;

    // capture visibility and normalise to starting at the fn keyword
    let visibility: (Visibility, Option<Span>) = match starting_token.token {
        // top-level function declarations can only be public or private, and private is implicit
        Token::Keyword(Keyword::Public) => {
            let (next_token, _) = get_next_token(
                tokens,
                next_index,
                previous_token,
                |next_token| match next_token.token {
                    Token::Keyword(Keyword::Function) => Ok(next_token),
                    _ => Err(Some(next_token)),
                },
                "Expected fn keyword.".to_owned(),
            )?;

            next_index += 1; // skip the fn keyword

            Ok((Visibility::Public, Some(next_token.span.clone())))
        },
        Token::Keyword(Keyword::Function) => Ok((Visibility::Private, None)),

        // unless we've done something wrong at development time, this should never happen
        _ => Err(Failure {
            message: "Expected one of the following keywords: fn, public.".to_string(),
            span: starting_token.span.clone(),
        }),
    }?;

    let (identifier, new_previous_token) = get_next_token(
        tokens,
        next_index,
        previous_token,
        |next_token| match &next_token.token {
            Token::Identifier(id) => Ok((id.to_owned(), next_token.span.clone())),
            _ => Err(Some(next_token)),
        },
        "Expected fn keyword.".to_owned(),
    )?;
    next_index += 1;
    previous_token = new_previous_token;

    // TODO split out everything below here into a separate function; it's re-usable for anonymous functions & class methods

    let (_, new_previous_token) = get_next_token(
        tokens,
        next_index,
        previous_token,
        |next_token| match &next_token.token {
            Token::Symbol(Symbol::ParenOpen) => Ok(next_token),
            _ => Err(Some(next_token)),
        },
        "Expected opening parenthesis.".to_owned(),
    )?;
    previous_token = new_previous_token;

    let (args, new_next_index) = parse_arguments(&file, &tokens, &next_index, &previous_token)?;
    next_index = new_next_index.clone();

    let (_, new_previous_token) = get_next_token(
        tokens,
        next_index,
        previous_token,
        |next_token| match &next_token.token {
            Token::Symbol(Symbol::Colon) => Ok(next_token),
            _ => Err(Some(next_token)),
        },
        "Expected colon.".to_owned(),
    )?;
    previous_token = new_previous_token;

    let (return_type, new_next_index) = parse_resolved_type(
        &file,
        &tokens,
        &next_index,
        previous_token,
    )?;
    next_index = new_next_index.clone();
    previous_token = &tokens[next_index - 1];

    // rather than retrieve the actual token here and do a check on it, we can just see if we've
    // got a code block or not - if we don't then we know there can't be an opening brace next and
    // the function's invalid
    let function_code_block = file.code_blocks.get(&next_index).ok_or(Failure {
        message: "Expected opening brace.".to_string(),
        span: previous_token.span.clone(),
    })?;

    let mut expressions: Vec<Expression> = vec![];

    while next_index < tokens.len() && &(next_index + 1) < &function_code_block.end {
        let (expr, new_next_index) = parse_expression(&file, &tokens, &next_index, previous_token)?;
        next_index = new_next_index;
        previous_token = &tokens[next_index - 1];

        expressions.push(expr);
    }

    // just skip checking the closing brace entirely, we know it's there because of the code block
    // and the lexing check
    previous_token = &tokens[next_index];
    next_index += 2;

    let new_function = DefinedFunction {
        identifier,
        visibility,
        arguments: args,
        expressions,
        returns: return_type,
    };

    file.functions.insert(
        new_function.identifier.0.clone(),
        Function::Regular(new_function),
    );

    return Ok(next_index);
}

