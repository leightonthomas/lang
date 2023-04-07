use crate::lexer::{get_next_token, SourceToken};
use crate::lexer::token::{Keyword, Symbol, Token};
use crate::parser::{Failure, File};
use crate::parser::expression::sub_expression::{parse_sub_expression, Precedence};
use crate::parser::expression::variable_declaration::parse_variable_declaration;
use crate::parser::statement::Expression;

pub mod variable_declaration;
pub mod sub_expression;

enum OperatorState {
    Prefix(Symbol),
}

/// Parse expressions:
/// * Variable declarations
/// * Return statements
/// * Sub-expressions
///
/// ## Arguments
/// * `file` - the file context
/// * `tokens` - the whole set of tokens for the file
/// * `starting_index` - the index of the keyword
/// * `starting_token` - the token for the keyword
///
/// ## Returns
/// A [Result] containing the arguments and the new index into the tokens after parsing the
/// arguments, or a failure.
pub fn parse_expression(
    file: &File,
    tokens: &Vec<SourceToken>,
    starting_index: &usize,
    starting_token: &SourceToken,
) -> Result<(Expression, usize), Failure> {
    let mut next_index = starting_index.clone() + 1;
    let mut previous_token: &SourceToken = starting_token;

    let (next_token, _) = get_next_token(
        tokens,
        next_index,
        previous_token,
        |n| Ok(n),
        "Expected an expression.".to_owned(),
    )?;

    if let Token::Keyword(Keyword::Let) = &next_token.token {
        let (variable_declaration, new_next_index) = parse_variable_declaration(
            file,
            tokens,
            &next_index,
            &next_token,
        )?;
        next_index = new_next_index;
        previous_token = &tokens[next_index - 1];

        check_end(tokens, &next_index, previous_token)?;

        return Ok((Expression::VariableDeclaration(variable_declaration), next_index));
    }

    if let Token::Keyword(Keyword::Return) = &next_token.token {
        let (sub_expression, new_next_index) = parse_sub_expression(
            file,
            tokens,
            &next_index,
            &next_token,
            Precedence::Default,
        )?;
        next_index = new_next_index;
        previous_token = &tokens[next_index - 1];

        check_end(tokens, &next_index, previous_token)?;

        return Ok((Expression::Return(sub_expression), next_index));
    }

    let (sub_expr, new_next_index) = parse_sub_expression(file, tokens, starting_index, starting_token, Precedence::Default)?;
    next_index = new_next_index;

    return Ok((Expression::SubExpression(sub_expr), next_index));
}

fn check_end(tokens: &Vec<SourceToken>, index: &usize, previous_token: &SourceToken) -> Result<(), Failure> {
    get_next_token(
        tokens,
        index.clone(),
        previous_token,
        |next_token| match next_token.token {
            Token::End => Ok(()),
            _ => Err(Some(next_token)),
        },
        "Expected end of statement.".to_string(),
    ).map(|_| ())
}

