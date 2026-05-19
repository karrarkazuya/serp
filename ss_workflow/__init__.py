# -*- coding: utf-8 -*-

from . import controllers
from . import models

def post_init_hook(env):
    # First we set the default general types
    departments = env['hr.department'].sudo().search([])
    for department in departments:
        department_in_tickets = env['ssw.departments'].sudo().search([(
            "department_id", "=", department.id
        )], limit=1)
        if not department_in_tickets:
            company = department.company_id
            env['ssw.departments'].sudo().create({
                "name": department.name + " ("+company.name+")",
                "company": company.id,
                "department_id": department.id
            })
            
    