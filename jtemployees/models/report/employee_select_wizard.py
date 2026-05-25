from odoo import models, fields


class EmployeeSelectWizard(models.TransientModel):
    _name = 'jtemployees.employee.select.wizard'
    _description = 'Select Employees Wizard'

    employee_ids = fields.Many2many(
        'hr.employee',
        string='Employees',
        domain="[('active', '=', True)]",
    )

    def action_apply(self):
        return {
            'type': 'ir.actions.act_window_close',
            'infos': {
                'selected_employee_ids': self.employee_ids.ids,
            },
        }
